<?php

namespace App\Http\Controllers;

use Hamcrest\Type\IsNumeric;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\Models\Pet;
use App\Models\Tag;
use App\Models\Category;
use App\Models\ApiResponse;

class PetController extends Controller
{
    /* Adds a new pet to the db, as categories and tags are not provided, if a tag is not in  tags table,  it will be added, 
    and the categories will be limited to cats, dogs, birds, rabbits, horses, ferrets, fish, guinea pigs, rats and mice, amphibians, reptiles.
    */
    public function addPet (Request $request) {


        try {
            $request->validate([
                'name' => 'required|string',
                'category' => 'required|integer',
                'photoUrls' => 'required|array',
                'tags' => 'required|array',
                'status' => 'required|string|in:available,pending,sold',
            ]);
        } catch(ValidationException $error) {
            $apiresponse = ApiResponse::where('code', 405 )->first();
            return response()->json([
                "message" =>  $apiresponse->message . " " . $error->getMessage() 
            ], 405);
        }
        

        $category = Category::find($request->category);
        $pet = new Pet;

        //checks if category id is a valid one, and if it is, stores the pet.

        if ($category) {
            // checks if the photo url is valid to add it to the url array.
            $valid_photo_urls = [];
            foreach($request->photoUrls as $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $type = exif_imagetype($url);
                
                if ($type === false) {
                    continue;
                }
                array_push($valid_photo_urls, $url);
            }
            $pet->name = strtolower(trim($request->name));
            $pet->category_id = strtolower(trim($request->category));
            $pet->photo_urls = json_encode($valid_photo_urls);
            $pet->status = strtolower(trim($request->status));
            $pet->save();
        } else {
            $apiresponse = ApiResponse::where('code', 405 )->first();
            return response()->json([
                "message" =>  $apiresponse->message . " " . "Invalid Category"
            ], 405);
        }

        //loops trhough the tags array so the not existing tags can be stored

        foreach($request->tags as $tag) {
            $tagModel = Tag::firstOrCreate(['name' => strtolower(trim($tag))]);
            $pet->tags()->attach($tagModel);
        }

        return response()->json([
            'id' => $pet->id,
            'name' => $pet->name,
            'category' => $pet->category_id,
            'photoUrls' => json_decode($pet->photo_urls),
            'tags' => $pet->tags->pluck('name'),
            'status' => $pet->status,
        ], 201);
    
    }

    //updates the entire pet information
     
    public function updatePet (Request $request) {


        try {
            $request->validate([
                'id' => 'required|integer',
                'name' => 'required|string',
                'category' => 'required|integer',
                'photoUrls' => 'required|array',
                'tags' => 'required|array',
                'status' => 'required|string|in:available,pending,sold',
            ]);
        } catch(ValidationException $error) {
            $apiresponse = ApiResponse::where('code', 405 )->first();
            return response()->json([
                "message" =>  $apiresponse->message . " " . $error->getMessage() 
            ], 405);
        }
        

        $category = Category::find($request->category);
        $pet = Pet::with('tags')->find($request->id);

        //checks if there is a per with the provided id and then 
        //checks if category id is a valid one

        if (!$pet) {
            $apiresponse = ApiResponse::where('code', 404)->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 404);
        }

        if ($category) {
            $valid_photo_urls = [];
            foreach($request->photoUrls as $url) {
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    continue;
                }

                $type = exif_imagetype($url);
                
                if ($type === false) {
                    continue;
                }
                array_push($valid_photo_urls, $url);
            }
            $pet->name = strtolower(trim($request->name));
            $pet->category_id = strtolower(trim($request->category));
            $pet->photo_urls = json_encode($valid_photo_urls);
            $pet->status = strtolower(trim($request->status));
        } else {
            $apiresponse = ApiResponse::where('code', 405 )->first();
            return response()->json([
                "message" =>  $apiresponse->message . " " . "Invalid Category."
            ], 405);
        }

        //loops trhough the tags array so the not existing tags can be stored, detach tags wich are no longer in the array provided by the user 

        $current_tags = $pet->tags->pluck('name');
        $current_tags = $current_tags->all();

        $req_tags = array_map(function ($string) {
            return strtolower(trim($string));
        }, $request->tags);

        foreach($current_tags as $tag) {
            $tagModel = Tag::where('name', strtolower(trim($tag)))->first();
            if (!in_array(strtolower($tag), $req_tags)) {
                $pet->tags()->detach($tagModel);
            } 
        }

        foreach($req_tags as $tag) {
            $tagModel = Tag::firstOrCreate(['name' => strtolower(trim($tag))]);
            if (!in_array(strtolower($tag), $current_tags)) {
                $pet->tags()->attach($tagModel);
            }
        }

        $pet->save();
    
        return response()->json(["message" => "Successful operation."], 200);
    
    }


    //retrieves a single pet using the specified id in the url params

    public function singlePet($id) {

        //makes sure the id provided is valid
        
        if (!is_numeric($id)) {
            $apiresponse = ApiResponse::where([ 'code' => 400, 'type' => 'find' ])->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 400);
        }

        $pet = Pet::with('tags')->find($id);

        if ($pet) {
            return response()->json([
                'id' => $pet->id,
                'name' => $pet->name,
                'category' => $pet->category_id,
                'photoUrls' => json_decode($pet->photo_urls),
                'tags' => $pet->tags->pluck('name'),
                'status' => $pet->status,
            ], 200);
        } else {
            $apiresponse = ApiResponse::where('code', 404)->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 404);
        }
    }

    //retrieves the pets with the matching status

    public function petsByStatus(Request $request) {

        //gets the status provided by user

        $status = explode(',', $request->input('status'));

        $valid_status = ['sold', 'available', 'pending'];
        $is_valid_status = true;

        for ($i = 0; $i < count($status); $i++) {
            $status[$i] = strtolower($status[$i]);
        }

        //makes sure status input is valid 
        
        foreach($status as $stat) {
            if (!in_array($stat, $valid_status)) {
                $is_valid_status = false;
            }
        }

        if (!$is_valid_status) {
            $apiresponse = ApiResponse::where([ 'code' => 400, 'type' => 'status'])->first();
            return response()->json([
                "message" =>  $apiresponse->message . '.'
            ], 405);
        }

        $pets = Pet::whereIn('status', $status)->get();

        // if there are no pets under any status means there are no pets in the database yet.

        if (count($status) === 3 && count($pets) === 0) {
            return response()->json([
                "message" => "There are no pets added yet."
            ], 200);
        }

        // if there are no pets with this status, handle response

        if (count($pets) === 0) {
            $apiresponse = ApiResponse::where('code', 404)->first();
            return response()->json([
                "message" =>  $apiresponse->message . ". No pets were found with this status."
            ], 404);
        }

        $response_pets = [];

        // gets the tags for each pet, and add the pet to response_pets

        foreach ($pets as $pet) {

            array_push($response_pets, [
                'id' => $pet->id,
                'name' => $pet->name,
                'category' => $pet->category_id,
                'photoUrls' => json_decode($pet->photo_urls),
                'tags' => $pet->tags()->pluck('name'),
                'status' => $pet->status,
            ]);

        }

        return response()->json($response_pets, 200);

    }

    //edits name and status fields

    public function editPet(Request $request, $id) {

        // verifies id

        if (!is_numeric($id)) {
            $apiresponse = ApiResponse::where([ 'code' => 400, 'type' => 'find' ])->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 400);
        }

        //verifies the user is updating at least one field with valid information

        try {
            $request->validate([
                'name' => 'required_without_all:status|string',
                'status' => 'required_without_all:name|string|in:available,pending,sold'
            ]);
        } catch(ValidationException $error) {
            $apiresponse = ApiResponse::where('code', 405 )->first();
            return response()->json([
                "message" =>  $apiresponse->message . " " . $error->getMessage() 
            ], 405);
        }

        $pet = Pet::find($id);


        if (!$pet) {
            $apiresponse = ApiResponse::where('code', 404)->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 404);
        }
        if ($request->name) $pet->name = trim(strtolower($request->name));
        if ($request->status) $pet->status = trim(strtolower($request->status));

        $pet->save();

        return response()->json([
            'id' => $pet->id,
            'name' => $pet->name,
            'category' => $pet->category_id,
            'photoUrls' => json_decode($pet->photo_urls),
            'tags' => $pet->tags->pluck('name'),
            'status' => $pet->status,
        ], 200);

    }

    //deletes the pet with the provided id

    public function deletePet($id) {

        if (!is_numeric($id)) {
            return response()->json([
                "message" => "Invalid ID supplied"
            ], 400);
        }

        $pet = Pet::find($id);

        if ($pet) {
            $pet->tags()->detach();
            $pet->delete();
            return response()->json(["message" => "Successful operation"], 200);
        } else {
            $apiresponse = ApiResponse::where('code', 404)->first();
            return response()->json([
                "message" =>  $apiresponse->message
            ], 404);
        }
    }
}
