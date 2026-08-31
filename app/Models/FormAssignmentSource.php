<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_assignment_id', 'form_publication_audience_source_id'])]
class FormAssignmentSource extends Model
{
    public $table = 'form_assignment_sources';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<FormAssignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FormAssignment::class, 'form_assignment_id');
    }

    /**
     * @return BelongsTo<FormPublicationAudienceSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(FormPublicationAudienceSource::class, 'form_publication_audience_source_id');
    }
}
