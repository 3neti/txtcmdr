<?php

namespace App\Services;

class MessagePersonalizer
{
    /**
     * Replace variables in message template with actual values
     *
     * @param  string  $template  Message template with {{variable}} placeholders
     * @param  array  $variables  Key-value pairs for substitution
     * @return string Personalized message
     */
    public function personalize(string $template, array $variables): string
    {
        $message = $template;

        foreach ($variables as $key => $value) {
            $placeholder = '{{'.$key.'}}';
            $message = str_replace($placeholder, (string) $value, $message);
        }

        return $message;
    }

    /**
     * Extract all variable placeholders from a template
     *
     * @param  string  $template  Message template
     * @return array List of variable names found
     */
    public function extractVariables(string $template): array
    {
        preg_match_all('/\{\{(\w+)\}\}/', $template, $matches);

        return $matches[1] ?? [];
    }
}
