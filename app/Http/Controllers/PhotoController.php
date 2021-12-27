<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Database;
use Kreait\Firebase\Storage;

class PhotoController extends Controller
{
    public function __construct(Database $database, Storage $storage)
    {
        $this->database = $database;
        $this->tableName = "photos";
        $this->storage = $storage;
        $this->storagePath = "photos/";
    }

    public function index()
    {
        $photos = $this->database->getReference($this->tableName)->getValue();
        return $photos;
    }

    public function store(Request $request)
    {
        info($request);
        $request->validate([
            "label" => "required|min:2|max:28",
            "url" => "required_without:image|url",
            "image" => "required_without:url|image|mimes:png,jpg,jpeg|max:512"
        ]);

        $newPostKey = $this->database->getReference($this->tableName)->push()->getKey();
        $optionKey = null;
        $optionValue = null;

        if ($request->hasFile("image")) {
            $optionKey = "file";
            $image = $request->file("image");
            $optionValue = $newPostKey . "." . $image->getClientOriginalExtension();
            $localFolder = public_path("firebase-temp-uploads") . "/";
            if ($image->move($localFolder, $optionValue)) {
                $uploadedfile = fopen($localFolder . $optionValue, "r");
                $this->storage->getBucket()->upload(
                    $uploadedfile,
                    [
                        "name" => $this->storagePath . $optionValue
                    ]
                );
                unlink($localFolder . $optionValue);
            } else {
                return response('Photo file not uploaded.', 400);
            }
        } else {
            $optionValue = $request->url;
            $optionKey = "url";
        }

        $postRef = $this->database->getReference()->update([
            $this->tableName . "/" . $newPostKey => [
                "label" => $request->label,
                $optionKey => $optionValue,
                "created_at" => now()
            ]
        ]);

        if ($postRef) {
            return response('Photo added successfully.', 200);
        } else {
            return response('Photo not added.', 400);
        }
    }

    public function destroy($id)
    {
        $photo = $this->database->getReference($this->tableName . "/" . $id);
        if (array_key_exists("file", $photo->getValue())) {
            $this->storage->getBucket()->object($this->storagePath . $photo->getValue()["file"])->delete();
        }
        $delData = $this->database->getReference($this->tableName . "/" . $id)->remove();
        if ($delData) {
            return response('Photo deleted successfully.', 200);
        } else {
            return response('Photo not deleted.', 400);
        }
    }
}
