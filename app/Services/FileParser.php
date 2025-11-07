<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

class FileParser
{
    public function parse(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension();

        return match ($extension) {
            'csv' => $this->parseCsv($file),
            'xlsx', 'xls' => $this->parseExcel($file),
            default => throw new \InvalidArgumentException('Unsupported file type'),
        };
    }

    public function parseFromPath(string $path): array
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'csv' => $this->parseCsvFromPath($path),
            'xlsx', 'xls' => $this->parseExcelFromPath($path),
            default => throw new \InvalidArgumentException('Unsupported file type'),
        };
    }

    private function parseCsv(UploadedFile $file): array
    {
        $csv = Reader::from($file->getRealPath());
        $csv->setHeaderOffset(0); // First row is header

        return iterator_to_array($csv->getRecords());
    }

    private function parseCsvFromPath(string $path): array
    {
        $csv = Reader::from($path);
        $csv->setHeaderOffset(0);

        return iterator_to_array($csv->getRecords());
    }

    private function parseExcel(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $worksheet = $spreadsheet->getActiveSheet();

        return $this->extractRows($worksheet);
    }

    private function parseExcelFromPath(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();

        return $this->extractRows($worksheet);
    }

    private function extractRows($worksheet): array
    {
        $rows = [];
        $headers = [];

        foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);

            $rowData = [];
            foreach ($cellIterator as $cell) {
                $rowData[] = $cell->getValue();
            }

            if ($rowIndex === 1) {
                // First row is header
                $headers = array_map('strtolower', array_map('trim', $rowData));
            } else {
                // Skip empty rows
                if (empty(array_filter($rowData))) {
                    continue;
                }

                $rows[] = array_combine($headers, $rowData);
            }
        }

        return $rows;
    }
}
