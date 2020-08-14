<?php

declare(strict_types=1);
include_once __DIR__ . '/stubs/Validator.php';
class SabotageueberwachungValidationTest extends TestCaseSymconValidation
{
    public function testValidateSabotageueberwachung(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }
    public function testValidateSabotageueberwachungModule(): void
    {
        $this->validateModule(__DIR__ . '/../Sabotageueberwachung');
    }
}