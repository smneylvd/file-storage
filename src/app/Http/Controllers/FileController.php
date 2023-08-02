<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;

class FileController extends Controller
{

    public function index(Request $req): JsonResponse
    {

        $validator = Validator::make($req->all(), [
            'name' => 'string',
            'page' => 'integer',
            'per_page' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors()->first(), 400);
        }

        try {
            $name = $req->filled('name') ? $req->name : null;
            $page = $req->filled('page') ? (int)$req->page : 1;
            $perPage = $req->filled('per_page') ? (int)$req->per_page : 50;

            $query = File::query();

            if (trim($name)) {
                $query->where("name", 'like', '%' . $name . '%');
            }

            $files = $query->offset(($page - 1) * $perPage)->limit($perPage)->get();
//            $files['total'] = count($files);
//            $files['per_page'] = $perPage;
//            $files['page'] = $page;
            return response()->json($files);
        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function upload(Request $req): JsonResponse
    {
        $validator = Validator::make($req->all(), [
            'file' => 'required|file|max:8192', // 8 MB in kilobytes
        ]);

        if ($validator->fails()) {
            return response()->json(["error" => $validator->errors()->first()], 400);
        }

        $file = $req->file('file');
        $name = $req->name ?? $file->getClientOriginalName();

        //additional check if user did not write type of file
        if ($req->name && count(explode('.', $req->name)) == 1) {
            $name .= '.' . explode('.', $file->getClientOriginalName())[1];
        }

        $size = floor($file->getSize() / 1024);
        $type = explode('.', $name)[1];

        try {
            $uploadedFile = Storage::disk("local")->put("files", $file);
            $newFile = File::create([
                "file_path" => Storage::url($uploadedFile),
                "name" => $name,
                "type" => $type,
                "size" => $size,
            ]);
            $newFile->save();
            return response()->json("success");

        } catch (Exception $exception) {
            return response()->json($exception->getMessage(), 500);
        }
    }

    public function delete($id)
    {
        $file = File::find($id);
        if ($file == null) {
            return response()->json("File not found", 404);
        }
        Storage::disk("local")->delete(str_replace("storage", "", $file->file_path));
        $file->delete();
        return response()->json("success");
    }

}
