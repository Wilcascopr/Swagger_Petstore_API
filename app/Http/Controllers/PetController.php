<?php

namespace App\Http\Controllers;

use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\Pet;
use App\Models\Tag;
use App\Models\Category;

class PetController extends Controller
{
    /* Adds a new pet to the db, as categories and tags are not provided, if a tag is not in  tags table,  it will be added, 
    and the categories will be limited to cats, dogs, birds, rabbits, horses, ferrets, fish, guinea pigs, rats and mice, amphibians, reptiles.
    */
    public function addPet (Request $req) {


        try {
            $req->validate([
                'name' => 'required|string',
                'category' => 'required|integer',
                'photoUrls' => 'required|array',
                'tags' => 'required|array',
                'status' => 'required|string|in:available,pending,sold',
            ]);
        } catch(ValidationException $e) {
            return response()->json([
                "message" => "Invalid input"
            ], 405);
        }
        

        $category = Category::find($req->category);
        $pet = new Pet;

        //checks if category id is a valid one

        if ($category) {
            $pet->name = $req->name;
            $pet->category_id = $req->category;
            $pet->photo_urls = json_encode($req->photoUrls);
            $pet->status = $req->status;
            $pet->save();
        } else {
            return response()->json([
                "message" => "Invalid input"
            ], 405);
        }

        //loops trhough the tags array so the not existing tags can be created

        foreach($req->tags as $tag) {
            $tagModel = Tag::firstOrCreate(['name' => strtolower($tag)]);
            $pet->tags()->attach($tagModel);
        }
    
        return response()->json([
            'id' => $pet->id,
            'name' => $pet->name,
            'category' => $pet->category_id,
            'photo_urls' => json_decode($pet->photo_urls),
            'tags' => $pet->tags(),
            'status' => $pet->status,
        ], 201);
    
    }
}
