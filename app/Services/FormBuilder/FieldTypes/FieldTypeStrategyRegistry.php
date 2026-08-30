<?php

namespace App\Services\FormBuilder\FieldTypes;

use Illuminate\Validation\ValidationException;

final class FieldTypeStrategyRegistry
{
    /** @var array<string, FieldTypeStrategy>|null */
    private ?array $strategiesByCode = null;

    public function __construct(
        private readonly DefaultFieldTypeStrategy $defaultStrategy,
        private readonly TextFieldTypeStrategy $textStrategy,
        private readonly NumberFieldTypeStrategy $numberStrategy,
        private readonly OptionsFieldTypeStrategy $optionsStrategy,
        private readonly RatingFieldTypeStrategy $ratingStrategy,
        private readonly ScaleFieldTypeStrategy $scaleStrategy,
        private readonly FileFieldTypeStrategy $fileStrategy,
        private readonly ContainerFieldTypeStrategy $containerStrategy,
        private readonly RichTextFieldTypeStrategy $richTextStrategy,
        private readonly RutFieldTypeStrategy $rutStrategy,
        private readonly DateRangeFieldTypeStrategy $dateRangeStrategy,
        private readonly EvaluationFieldTypeStrategy $evaluationStrategy,
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $validationRules
     * @return array{settings: array<string, mixed>, validation_rules: array<string, mixed>, default_value: mixed}
     */
    public function validateAndNormalize(
        string $code,
        array $settings,
        array $validationRules,
        mixed $defaultValue = null,
    ): array {
        $strategy = $this->strategies()[$code] ?? null;

        if ($strategy === null) {
            throw ValidationException::withMessages([
                'field_type_id' => "El tipo de campo '{$code}' no está soportado por el constructor.",
            ]);
        }

        return $strategy->validateAndNormalize($code, $settings, $validationRules, $defaultValue);
    }

    /** @return array<string, FieldTypeStrategy> */
    private function strategies(): array
    {
        if ($this->strategiesByCode !== null) {
            return $this->strategiesByCode;
        }

        $this->strategiesByCode = [];
        $strategies = [
            $this->defaultStrategy,
            $this->textStrategy,
            $this->numberStrategy,
            $this->optionsStrategy,
            $this->ratingStrategy,
            $this->scaleStrategy,
            $this->fileStrategy,
            $this->containerStrategy,
            $this->richTextStrategy,
            $this->rutStrategy,
            $this->dateRangeStrategy,
            $this->evaluationStrategy,
        ];

        foreach ($strategies as $strategy) {
            foreach ($strategy->supportedCodes() as $code) {
                $this->strategiesByCode[$code] = $strategy;
            }
        }

        return $this->strategiesByCode;
    }
}
