<?php

namespace App\Http\Controllers;

use App\Category;
use App\Product;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,staff');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $category = Category::orderBy('name','ASC')
            ->get()
            ->pluck('name','id');

        $producs = Product::all();
        return view('products.index', compact('category'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $category = Category::orderBy('name','ASC')
            ->get()
            ->pluck('name','id');

        $this->validate($request , [
            'nama'          => 'required|string',
            'harga'         => 'required',
            'qty'           => 'required',
            'image'         => 'required',
            'category_id'   => 'required',
        ]);

        $input = $request->all();
        $input['image'] = null;

        if ($request->hasFile('image')){
            $input['image'] = '/upload/products/'.str_slug($input['nama'], '-').'.'.$request->image->getClientOriginalExtension();
             // Store the file in the 'uploads' folder on S3
             $fileName = 'products/' . str_slug($input['nama'], '-') . '.' . $request->image->getClientOriginalExtension();

             $path = $request->file('image')->storeAs('uploads', $fileName, 's3');

            // Save the S3 path to the 'image' field
            $input['image'] = Storage::disk('s3')->url($path);
            // $request->image->move(public_path('/upload/products/'), $input['image']);
        }

        Product::create($input);

        return response()->json([
            'success' => true,
            'message' => 'Products Created'
        ]);

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $category = Category::orderBy('name','ASC')
            ->get()
            ->pluck('name','id');
        $product = Product::find($id);
        return $product;
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $category = Category::orderBy('name','ASC')
            ->get()
            ->pluck('name','id');

        $this->validate($request , [
            'nama'          => 'required|string',
            'harga'         => 'required',
            'qty'           => 'required',
//            'image'         => 'required',
            'category_id'   => 'required',
        ]);

        $input = $request->all();
        $produk = Product::findOrFail($id);

        $input['image'] = $produk->image;

        // if ($request->hasFile('image')){
        //     if (!$produk->image == NULL){
        //         unlink(public_path($produk->image));
        //     }
        //     $input['image'] = '/upload/products/'.str_slug($input['nama'], '-').'.'.$request->image->getClientOriginalExtension();
        //     $request->image->move(public_path('/upload/products/'), $input['image']);
        // }

        if ($request->hasFile('image')) {
            // Check if the product already has an existing image and delete it from S3
            if ($produk->image !== null) {
                // Extract the S3 file path from the image URL and delete it
                $oldImagePath = str_replace(Storage::disk('s3')->url(''), '', $produk->image);
                Storage::disk('s3')->delete($oldImagePath);
            }

            // Generate the new file path with a slugified name
            $fileName = 'products/' . str_slug($input['nama'], '-') . '.' . $request->image->getClientOriginalExtension();

            // Store the new image on S3 and get the path
            $path = $request->file('image')->storeAs('uploads', $fileName, 's3');

            // Save the public URL of the uploaded image in the 'image' field
            $input['image'] = Storage::disk('s3')->url($path);
        }


        $produk->update($input);

        return response()->json([
            'success' => true,
            'message' => 'Products Update'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        if (!$product->image == NULL){
            // unlink(public_path($product->image));
            $imagePath = str_replace(Storage::disk('s3')->url(''), '', $product->image);
            Storage::disk('s3')->delete($imagePath);
        }

        Product::destroy($id);

        return response()->json([
            'success' => true,
            'message' => 'Products Deleted'
        ]);
    }

    public function apiProducts(){
        $product = Product::all();

        return Datatables::of($product)
            ->addColumn('category_name', function ($product){
                return $product->category->name;
            })
            ->addColumn('show_photo', function($product){
                if ($product->image == NULL){
                    return 'No Image';
                }
                return '<img class="rounded-square" width="50" height="50" src="'. url($product->image) .'" alt="">';
            })
            ->addColumn('action', function($product){
                return'<a onclick="editForm('. $product->id .')" class="btn btn-primary btn-xs"><i class="glyphicon glyphicon-edit"></i> Edit</a> ' .
                    '<a onclick="deleteData('. $product->id .')" class="btn btn-danger btn-xs"><i class="glyphicon glyphicon-trash"></i> Delete</a>';
            })
            ->rawColumns(['category_name','show_photo','action'])->make(true);

    }
}
