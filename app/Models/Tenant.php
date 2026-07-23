<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = ['name', 'default_template_id', 'sms_template', 'sms_template_name'];

    public function defaultTemplate()
    {
        return $this->belongsTo(Template::class, 'default_template_id');
    }
}
