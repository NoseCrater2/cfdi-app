<?php

namespace App\Services\Cfdi;

use DOMDocument;
use RuntimeException;

class CfdiXmlValidator {

    function validate(DOMDocument $document, string $xsdPath): array {

        if(!file_exists($xsdPath)){
            throw new RuntimeException("No se encontró el archivo XSD: {$xsdPath}");
        }

        $previous = libxml_use_internal_errors(true);

        libxml_clear_errors();

        try {
            $isValid = $document->schemaValidate($xsdPath);
            $errors = [];

            foreach (libxml_get_errors() as $error) {
                $errors[] = [
                    'level' => $this->getLevel($error->level),
                    'code' => $error->code,
                    'line' => $error->line,
                    'column' => $error->column,
                    'message' => trim($error->message)
                ];
            }

            return [
                'valid' => $isValid,
                'errors' => $errors,
            ];

        } finally {
           libxml_clear_errors();
           libxml_use_internal_errors($previous);
        }
    }

    private function getLevel(int $level) : string {
        return match ($level) {
            LIBXML_ERR_WARNING => 'warning',
            LIBXML_ERR_ERROR => 'error',
            LIBXML_ERR_FATAL => 'fatal',
            default => 'unknown'
        };
    }

    public function formatErrors(array $errors): string
{
    if (empty($errors)) {
        return '';
    }

    return collect($errors)
        ->map(function (array $error) {
            return sprintf(
                '[%s] Línea %d, columna %d: %s',
                strtoupper($error['level']),
                $error['line'],
                $error['column'],
                $error['message']
            );
        })
        ->implode(PHP_EOL);
}
}
