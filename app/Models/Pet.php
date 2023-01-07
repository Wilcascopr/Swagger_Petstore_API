<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Tag;

class Pet extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status', 'photo_urls', 'category_id'];

    public function tags() {
        return $this->belongsToMany(Tag::class, 'pet_tag', 'pet_id', 'tag_id');
    }
}
