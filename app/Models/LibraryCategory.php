<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryCategory extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id'];

    public function documents() { return $this->hasMany(LibraryDocument::class, 'category_id'); }
    public function parent()    { return $this->belongsTo(self::class, 'parent_id'); }
    public function children()  { return $this->hasMany(self::class, 'parent_id'); }
}
