<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Event extends Model
{
    use HasFactory;

    protected $table = 'events';

    protected $fillable = [
        'nama_event',
        'deskripsi',
        'tgl_event',
        'organizer_id',
        'waktu',
        'harga_vip',
        'harga_reg',
        'lokasi',
        'seats',
        'thumbnail',
        'kapasitas_vip',
        'kapasitas_reg',
        'kategori',
        'status',
        'file_proposal',
        'is_certificate_published',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class, 'organizer_id'); 
    }
}
