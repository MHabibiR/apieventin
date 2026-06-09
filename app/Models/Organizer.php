<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organizer extends Model
{
    use HasFactory;

    protected $table = 'organizer';

    protected $fillable = [
        'user_id',
        'nama_eo',
        'file_proposal',
        'status',
        'no_rekening',
        'atas_nama_rekening',
        'nama_bank',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function events()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }
}
