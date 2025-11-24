<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocPdfExtranjero extends Model
{
    protected $table = '';

    protected $fillable = [
        'business_registration_id',
        'antecedentes_penales_pdf',
        'antecedentes_policiales_pdf'
    ];

    public function businessRegistration()
    {
        return $this->belongsTo(BusinessRegistration::class);
    }
}
