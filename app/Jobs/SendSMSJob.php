<?php

namespace App\Jobs;

use App\Exceptions\SmsConfigurationException;
use App\Jobs\Middleware\CheckBlacklist;
use App\Models\MessageLog;
use App\Services\SmsConfigService;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\SMS\Facades\SMS;
use Propaganistas\LaravelPhone\PhoneNumber;

class SendSMSJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $mobile,
        public string $message,
        public string $senderId = 'TXTCMDR',
        public ?int $scheduledMessageId = null,
        public ?int $userId = null
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new CheckBlacklist($this->mobile)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Normalize phone number to E.164
        try {
            $phone = new PhoneNumber($this->mobile, 'PH');
            $e164Mobile = $phone->formatE164();
        } catch (\Exception $e) {
            $e164Mobile = $this->mobile;
        }

        // Create message log
        $log = MessageLog::create([
            'user_id' => $this->userId ?? auth()->id() ?? 1, // Use passed userId, fallback to auth, then admin
            'recipient' => $e164Mobile,
            'message' => $this->message,
            'status' => 'pending',
            'sender_id' => $this->senderId,
            'scheduled_message_id' => $this->scheduledMessageId,
        ]);

        try {
            // Get user-specific or app-wide SMS config
            $user = $this->userId ? \App\Models\User::find($this->userId) : null;
            $smsConfigService = app(SmsConfigService::class);
            $config = $smsConfigService->getEngageSparkConfig($user);

            // Validate credentials are present
            if (! $config['api_key'] || ! $config['org_id']) {
                throw SmsConfigurationException::missingCredentials($this->userId);
            }

            // Set runtime config for EngageSPARK
            config([
                'engagespark.api_key' => $config['api_key'],
                'engagespark.org_id' => $config['org_id'],
            ]);

            // Send SMS using resolved config
            SMS::channel('engagespark')
                ->from($this->senderId)
                ->to($this->mobile)
                ->content($this->message)
                ->send();

            // Mark as sent
            $log->markAsSent();
        } catch (ClientException $e) {
            // Handle HTTP client errors (401, 403, etc.)
            $statusCode = $e->getResponse()->getStatusCode();
            $errorMessage = match ($statusCode) {
                401 => 'Authentication failed: Invalid or expired API credentials',
                403 => 'Access forbidden: Check API permissions and account status',
                429 => 'Rate limit exceeded: Too many requests',
                default => "HTTP {$statusCode}: ".$e->getMessage(),
            };

            $log->markAsFailed($errorMessage);
            
            // Wrap in more descriptive exception
            if ($statusCode === 401) {
                throw SmsConfigurationException::authenticationFailed('EngageSPARK');
            }
            
            throw new \RuntimeException($errorMessage, $statusCode, $e);
        } catch (SmsConfigurationException $e) {
            // Handle configuration errors with user-friendly message
            $log->markAsFailed($e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            // Handle all other errors
            $errorMessage = 'SMS sending failed: '.$e->getMessage();
            $log->markAsFailed($errorMessage);
            throw new \RuntimeException($errorMessage, 0, $e);
        }
    }
}
