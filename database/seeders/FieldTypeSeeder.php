<?php

namespace Database\Seeders;

use App\Models\FieldType;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FieldTypeSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->fieldTypes() as $fieldType) {
            FieldType::query()->updateOrCreate(
                ['code' => $fieldType['code']],
                $fieldType,
            );
        }
    }

    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     has_options: bool,
     *     is_container: bool,
     *     is_active: bool
     * }>
     */
    private function fieldTypes(): array
    {
        return [
            $this->fieldType('text', 'Texto'),
            $this->fieldType('textarea', 'Texto largo'),
            $this->fieldType('number', 'Número'),
            $this->fieldType('email', 'Correo electrónico'),
            $this->fieldType('phone', 'Teléfono'),
            $this->fieldType('date', 'Fecha'),
            $this->fieldType('time', 'Hora'),
            $this->fieldType('datetime', 'Fecha y hora'),
            $this->fieldType('select', 'Lista desplegable', hasOptions: true),
            $this->fieldType('multiselect', 'Selección múltiple', hasOptions: true),
            $this->fieldType('radio', 'Selección única', hasOptions: true),
            $this->fieldType('checkbox', 'Casillas de verificación', hasOptions: true),
            $this->fieldType('file', 'Archivo'),
            $this->fieldType('signature', 'Firma'),
            $this->fieldType('rating', 'Calificación'),
            $this->fieldType('scale', 'Escala'),
            $this->fieldType('table', 'Tabla', isContainer: true),
            $this->fieldType('section', 'Sección', isContainer: true),
            $this->fieldType('paragraph', 'Texto informativo'),
            $this->fieldType('rich_text', 'Texto enriquecido'),
            $this->fieldType('currency', 'Moneda'),
            $this->fieldType('percentage', 'Porcentaje'),
            $this->fieldType('url', 'URL'),
            $this->fieldType('rut', 'RUT'),
            $this->fieldType('autocomplete', 'Autocompletar', hasOptions: true),
            $this->fieldType('yes_no', 'Si / No'),
            $this->fieldType('date_range', 'Rango de fechas'),
            $this->fieldType('slider', 'Slider'),
            $this->fieldType('nps', 'NPS'),
            $this->fieldType('likert', 'Likert', hasOptions: true),
            $this->fieldType('repeatable_group', 'Grupo repetible', isContainer: true),
            $this->fieldType('divider', 'Separador'),
        ];
    }

    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     has_options: bool,
     *     is_container: bool,
     *     is_active: bool
     * }
     */
    private function fieldType(
        string $code,
        string $name,
        bool $hasOptions = false,
        bool $isContainer = false,
    ): array {
        return [
            'code' => $code,
            'name' => $name,
            'has_options' => $hasOptions,
            'is_container' => $isContainer,
            'is_active' => true,
        ];
    }
}
