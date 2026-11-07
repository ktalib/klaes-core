<?php

namespace Tests\Unit;

use Tests\TestCase;

class SurveyItsComputationFileTest extends TestCase
{
    public function test_computation_file_selection_backfills_file_number_without_required_validation(): void
    {
        $form = file_get_contents(resource_path('views/survey_module/partials/sections/_page-gkn-its-form.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/Survey/SurveyItsController.php'));

        $this->assertStringContainsString('<label>File Number</label>', $form);
        $this->assertStringContainsString('data-file-number="{{ $cf->file_number }}"', $form);
        $this->assertStringContainsString('data-file-number="{{ $fi->file_number }}"', $form);
        $this->assertStringContainsString("fileNumber.value = o.dataset.fileNumber", $form);
        $this->assertStringNotContainsString('Select the file number first.', $form);
        $this->assertStringContainsString("'file_number' => 'nullable|string|max:100'", $controller);
        $this->assertStringContainsString('$data[\'file_number\'] = $computationFileNumber ?: trim((string) ($data[\'file_number\'] ?? \'\'));', $controller);
        $this->assertStringNotContainsString("'file_number.required'", $controller);
    }

    public function test_instruction_form_blade_compiles(): void
    {
        $source = file_get_contents(resource_path('views/survey_module/gkn/its/form.blade.php'));
        $partial = file_get_contents(resource_path('views/survey_module/partials/sections/_page-gkn-its-form.blade.php'));

        foreach ([$source, $partial] as $blade) {
            $compiled = app('blade.compiler')->compileString($blade);
            $this->assertNotFalse(token_get_all($compiled, TOKEN_PARSE));
        }
    }
}
