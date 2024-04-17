<?php

namespace App\Http\Controllers;

use App\Models\AccessToken;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

use App\Models\User;
use App\Models\Email;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductComment;
use App\Models\ProductPhoto;
use App\Models\ProductRaiting;
use App\Models\ProductRating;
use App\Models\Purchase;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class UserController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function Loginfailed(){
        return response() -> json([
            "Status"=> 403,
            "Content-Type"=> "application/json",
            "Body"=>
            [
               "message"=> "Login failed"
            ]
        ], 403);
    }

    public function Forbiddenforyou(){
        return response() -> json([
            "Status" => 403,
            "Content-Type" => "application/json",
            "Body" =>
            [
               "message" => "Forbidden for you"
            ]
        ],403);
    }

    public function Notfound(){
        return response() -> json([
            "Status" => 404,
            "Content-Type" => "application/json",
            "Body" =>
            [
                "message" => "Not found"
            ]
        ],404);
    }

    public function validatorFails($validator){
        return response() -> json([
            "Status" => 422,
            "Content-Type" => "application/json",
            "Body" =>
            [
            "success" => false,
            "message" => $validator -> errors()
            ]
        ],422);
    }

    // -----------------------------------------------

    // Аунтификация
    public function authorization(Request $request){
        // Получаем данные из запроса
        $data = $request->only('login', 'password');
        $login = $data['login'];
        $password = $data['password'];

        // Проверяем наличие пользователя по email
        $user = User::where('login', $login)->first();

        if (!$user) {
            return $this -> Loginfailed();
        } else {
            // Проверка пароля с использованием хеширования
            if (!Hash::check($password, $user->password)) {
                return $this -> Loginfailed();
            } else {
                // Находим или создаем токен для пользователя
                $token = AccessToken::updateOrCreate(
                    ['user_id' => $user->id],
                    ['token' => bin2hex(openssl_random_pseudo_bytes(16))]
                );

                return response()->json([
                    'status' => 200,
                    'Content-Type' => 'application/json',
                    'body' => [
                        'success' => true,
                        'message' => 'Success',
                        'token' => $token->token
                    ]
                ],200);
            }
        }
    }

    // регистрация
    public function registration(Request $request){
        $data = $request->only('name', 'login', 'password', 'email');

        $validator = Validator::make($data, [
            "name" => 'required',
            "login" => 'required',
            "password" => 'required|min:3|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/',
            "email" => 'required|unique:emails,email',
        ]);

        if ($validator->fails()) {
            return $this->validatorFails($validator);
        } else {
            $newUser = new User();
            $newUser->name = $data['name'];
            $newUser->login = $data['login'];
            $newUser->password = Hash::make($data['password']);
            $newUser->role = 333;
            $newUser->save();

            $newEmail = new Email();
            $newEmail->user_id = $newUser->id;
            $newEmail->email = $data['email'];
            $newEmail->save();

            return response() -> json([
                "status"=>200,
                'Content-Type' => 'application/json',
                "body"=>[
                    "success" => true,
                    "message"=>"Success",
                ]
            ],200);
        }
    }

    // выйти из аккаунта
    public function logout(Request $request){
        $bearerToken = $request -> bearerToken();

        $token = AccessToken::where('token',$bearerToken)->first();
        $user = User::where('id',$token -> user_id) -> first();

        if (!$user || !$token || $token->token === null) {
            return $this -> Loginfailed();
        } else {
            $token -> token = null;
            $token -> save();
            return response() -> json([
                "Status"=>200,
                "Content-Type"=>"application/json",
                "Body"=>[
                    "success" => true,
                    "message"=>"Logout",
                ]
            ],200);
        }
    }
    // является ли пользователь админом
    public function isAdmin(Request $request){
        $bearerToken = $request -> bearerToken();

        $token = AccessToken::where('token',$bearerToken)->first();
        if(!$token){
            return $this -> Loginfailed();
        }

        $user = User::where('id',$token -> user_id) -> first();

        if (!$user || !$token || $token->token === null) {
            return $this -> Loginfailed();
        } else {
            if($user -> role === 777){ 
            return response() -> json([
                "Status"=>200,
                "Content-Type"=>"application/json",
                "Body"=>[
                    "success" => true,
                ]
            ],200);
            } else {
                return $this -> Forbiddenforyou();
            }
            
        }
    }
    // является ли пользователь продавцом
    public function isSeller(Request $request){
        $bearerToken = $request -> bearerToken();

        $token = AccessToken::where('token',$bearerToken)->first();
        if(!$token){
            return $this -> Loginfailed();
        }

        $user = User::where('id',$token -> user_id) -> first();

        if (!$user || !$token || $token->token === null) {
            return $this -> Loginfailed();
        } else {
            if($user -> role === 555 || $user -> role === 777){ 
            return response() -> json([
                "Status"=>200,
                "Content-Type"=>"application/json",
                "Body"=>[
                    "success" => true,
                ]
            ],200);
            } else {
                return $this -> Forbiddenforyou();
            }
            
        }
    }

    // Продавец может
    public function createProduct(Request $request){
        // Your existing code
        $bearerToken = $request->bearerToken();

        // Check if bearer token is present
        if ($bearerToken === null) {
            return $this->Loginfailed();
        }
        $token = AccessToken::where('token', $bearerToken)->first();
        // Check if token exists and has user_id property
        if (!$token || $token === null || !$token->user_id) {
            return $this->Loginfailed();
        } else {
            $user = User::where('id', $token -> user_id) -> first();
            if(!$user){
                return $this->Loginfailed();
            }
            if($user -> role === 333){
                return $this -> Forbiddenforyou();
            }

            $validator = Validator($request -> all(),[
                "name" => 'required',
                "category_code" => [
                    'required',
                    function ($attribute, $value, $fail) {
                        if (!DB::table('product_categories')->where('code', $value)->exists()) {
                            $fail("The selected category is invalid.");
                        }
                    },
                ],
                "count" => 'required',
                "price" => 'required',
                "description" => 'required',
                'files'=>'required'
            ]);
            if( $validator -> fails()){
                return $this -> validatorFails($validator);
            }
            $new_product = new Product();
            $new_product -> name = $request -> input('name');
            $new_product -> category_code = ProductCategory::where('code', $request -> input('category_code')) ->first() -> id;
            $new_product -> count = $request -> input('count');
            $new_product -> description = $request -> input('description');
            $new_product -> price = $request -> input('price');
            $new_product -> save();

            $directory = 'public/files';
            $files = $request->file('files');
            $responseArray = [];
            foreach ($files as $file) {
                if ($file) {
                    $count = 0;
                    $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $newName = $fileName . '.' . $file->getClientOriginalExtension();
                    while(ProductPhoto::where('name', $newName)->first()){
                        $count++;
                        $newName = $fileName . '(' . $count . ').' . $file->getClientOriginalExtension();
                    }
        
                    // Save the file to the public directory
                    $file->move(public_path($directory), $newName);
        
                    // Generate URL for the file
                    $url = asset($directory . '/' . $newName);
                    $newFile = new ProductPhoto();
                    $newFile->name = $newName;
                    $newFile->product_id = $new_product->id;
                    $newFile->url = $url;
                    $newFile->save();
        
                    
        
                    $responseArray[] = [
                        "success" => true,
                        "message" => "Success",
                        "name" => $newFile->name,
                        "file_id" => $newFile->file_id,
                        "url" => $url,
                    ];
                }
            }
            return response()->json([
                "status" => 200,
                "Content-Type" => "application/json",
                "body" => [
                    "product" => $new_product,
                    "product_photos" => $responseArray // Corrected typo here
                ]
            ]);
        }
    }

    public function getAllProduct(Request $request){
        $all_productsArray = [];

        foreach(Product::all() as $product){
            $all_productsRaitingArray = [];
            $raiting_count = 0;
            $raiting_value = 0;
            foreach(ProductRating::where('product_id', $product -> id) -> get() as $raiting){
                $raiting_count++;
                $raiting_value += $raiting -> value;
            }
            $raiting_count === 0 ? 0 : $raiting_value % $raiting_count;
            $average_rating = $raiting_count > 0 ? number_format($raiting_value / $raiting_count, 1) : 0.0;

            $all_productsCommentsArray = [];
            foreach(ProductComment::where('product_id', $product -> id) -> get() as $comment){
                $user_comment = User::where('id', $comment -> user_id) -> first();
                $all_productsCommentsArray[] = [
                    'user' => $user_comment,
                    "comment" => $comment
                ];
            }

            $all_productsPhotos = [];
            foreach(ProductPhoto::where('product_id', $product -> id) -> get() as $photos){
                $all_productsPhotos[] = $photos;
            }
            $all_productsArray[] = [
                'product' => $product,
                'photos' => $all_productsPhotos,
                'raiting' => $average_rating,
                'comments' => $all_productsCommentsArray,
            ];
        }

        return response() -> json([
            'body'=>$all_productsArray
        ],200);
    }

    // Детали об одном товаре
    public function getProductDetails($product_id){
        $product = Product::find($product_id);
    
        if(!$product){
            // Handle case when product is not found
            return response()->json(['error' => 'Product not found'], 404);
        }
    
        $ratingCount = 0;
        $ratingValue = 0;
        $productRatings = ProductRating::where('product_id', $product_id)->get();
    
        foreach ($productRatings as $rating) {
            $ratingCount++;
            $ratingValue += $rating->value;
        }
    
        $averageRating = $ratingCount === 0 ? 0 : $ratingValue / $ratingCount;
    
        $comments = [];
        $productComments = ProductComment::where('product_id', $product_id)->get();
    
        foreach ($productComments as $comment) {
            $userComment = User::find($comment->user_id);
            $comments[] = [
                'user' => $userComment,
                'comment' => $comment
            ];
        }
    
        $photos = ProductPhoto::where('product_id', $product_id)->get();
    
        $productDetails = [
            'product' => $product,
            'photos' => $photos,
            'rating' => $ratingCount,
            'average_rating' => $averageRating,
            'comments' => $comments,
        ];
    
        return response()->json([
            'body' => $productDetails
        ], 200);
    }
    // Оставить коментарий
    public function postProductComment($product_id, Request $request){
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
        $comment = $request->input('comment');
        if (!$token){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            if (!$user){
                return $this->Loginfailed();
            } else {
                $product = Product::where('id', $product_id) -> first();

                if (!$product){
                    return $this->Notfound();
                } else {
                    $validator = Validator::make($request -> all(),[
                        "comment" => 'required'
                    ]);
                    if($validator -> fails()){
                        return $this -> validatorFails($validator);
                    }
                    $newFileComment = new ProductComment();
                    $newFileComment -> product_id = $product_id;
                    $newFileComment -> user_id = $user -> id;
                    $newFileComment -> comment = $comment;
                    $newFileComment -> save();

                    $file_comments = [];

                    foreach(ProductComment::where('product_id', $product_id) -> get() as $fc){
                        $file_comments[] = $fc;
                    }

                    return response()->json([
                        "status" => 200,
                        "Content-Type" => "application/json",
                        "body" =>  $file_comments
                    ], 200);
                }
            }
        }

    }
    // Оставить оценку
    public function postProductRaiting($product_id, Request $request){
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
        $raiting_value = $request->input('raiting_value');
    
        if (!$token){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            if (!$user){
                return $this->Loginfailed();
            } else {
                $file = Product::where('id', $product_id)->first();
                
                if (!$file){
                    return $this->Notfound();
                } else {
                        $validator = Validator::make($request -> all(),[
                            "raiting_value" => 'required|integer|min:0'
                        ]);
                        if($validator -> fails()){
                            return $this -> validatorFails($validator);
                        }
                        if(ProductRating::where('product_id', $product_id) -> where('user_id', $user->id) -> first()){
                            return response() -> json([
                                "Status" => 400,
                                "Content-Type" => "application/json",
                                "Body" => 'User has already voted for this file.'
                            ], 400);
                        } else {
                            $newFileRaiting = new ProductRating();
                            $newFileRaiting -> product_id = $product_id;
                            $newFileRaiting -> user_id = $user -> id;
                            $newFileRaiting -> value = $raiting_value;
                            $newFileRaiting -> save();
                            
                            $file_rating = 0;
                                $file_rating_count = 0;
    
                                foreach (ProductRating::where('product_id', $product_id)->get() as $fr) {
                                    $file_rating += $fr->value;
                                    $file_rating_count++;
                                }
    
                                $average_rating = $file_rating_count > 0 ? number_format($file_rating / $file_rating_count, 1) : 0.0;
    
                            return response()->json([
                                "Status" => 200,
                                "Content-Type" => "application/json",
                                "Body" =>  $average_rating
                            ], 200);
                        }
                }
            }
        }
    }

    // Совергить покупку
    public function makePurchase($product_id, Request $request){
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
    
        if (!$token || $token === null){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            if (!$user){
                return $this->Loginfailed();
            } else {
                $product = Product::where('id', $product_id)->first();
                if (!$product){
                    return $this->Notfound();
                } else {
                    $count = $request -> count;
                    $new_Purchase = new Purchase();
                    $new_Purchase -> user_id = $user -> id;
                    $new_Purchase -> product_id = $product_id;
                    $new_Purchase -> count = $count;
                    $new_Purchase -> price = $product -> price;
                    $new_Purchase -> save();

                    $product -> count = $product -> count - $count;
                    $product -> save(); 

                    $all_Purchase = [];
                    foreach(Purchase::where('user_id', $user -> id) -> get() as $purch){
                        $all_Purchase[] = $purch;
                    }
                    return response() -> json([
                        'status'=>200,
                        "body"=>$all_Purchase
                    ],200);
                }

            }
        }
    }

    public function getAllPurchases(Request $request){
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
    
        if (!$token || $token === null){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            
            if (!$user){
                return $this->Loginfailed();
            } else {
                $allPurchases = [];
                foreach(Purchase::where('user_id', $user -> id) ->get() as $purch){
                    
                    $product = Product::where('id', $purch -> product_id) -> first();
                    // return response() -> json($product);
                    // Check if the product exists before accessing its properties
                    if($product){
                        $allPurchases[] = [
                            "count" => $purch -> count ,
                            "created_at" => $purch -> created_at,
                            "id" => $purch -> id ,
                            "price"=> $purch -> price ,
                            "product_id" => $purch -> product_id ,
                            "updated_at"  => $purch -> updated_at,
                            "user_id"  => $purch -> user_id,
                            'name' => $product -> name
                        ];
                    }
                }
                return response() -> json([
                    'body' => $allPurchases
                ]);
            }
        }
    }
    

    public function getAllCategories(Request $request){
        $AllCategories = [];
        foreach(ProductCategory::all() as $AC){
            $AllCategories[] = [
                'name' => $AC->name,
                'code' => $AC->code,
            ];
        }
    
        return response()->json($AllCategories);
    }
    
    public function getAllUsers(Request $request)
    {
        // Check if bearer token exists and retrieve user ID
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
    
        if (!$token || $token === null){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            if (!$user){
                return $this->Loginfailed();
            } else {
                $bearerUserId = $token->user_id; // Corrected variable name
    
                // Retrieve all users except the one identified by the bearer token
                $allUsers = User::where('id', '!=', $bearerUserId)->get();
    
                // Prepare response data
                $formattedUsers = [];
    
                foreach ($allUsers as $user) { 
                    $userPurchasesArray = [];
    
                    // Retrieve purchases for the current user
                    $userPurchases = Purchase::where('user_id', $user->id)->get();
    
                    // Retrieve product details for each purchase
                    foreach($userPurchases as $purchase){
                        $product = Product::where('id', $purchase->product_id)->first();
    
                        // Combine purchase and product information
                        $combinedPurchase = [
                            'purchase_id' => $purchase->id,
                            'product_name' => $product->name,
                            'count' => $purchase->count,
                            'price' => $purchase->price,
                            // Add more fields as needed
                        ];
    
                        // Add combined purchase to userPurchasesArray
                        $userPurchasesArray[] = $combinedPurchase;
                    }
    
                    // Add user and their purchases to formattedUsers
                    $formattedUsers[] = [
                        'user' => $user,
                        'purchases' => $userPurchasesArray
                    ];
                }
    
                return response()->json($formattedUsers);
            }
        }
    }
    
    public function deletePurchase(Request $request){
        // Check if bearer token exists and retrieve user ID
        $bearerToken = $request->bearerToken();
        $token = AccessToken::where('token', $bearerToken)->first();
    
        if (!$token || $token === null){
            return $this->Loginfailed();
        } else {
            $user = User::where("id", $token->user_id)->first();
            if (!$user){
                return $this->Loginfailed();
            } else {
                $user_id  = $request -> input('user_id');
                $product_id = $request -> input('product_id');
    
                // Find the purchase to delete
                $purchase = Purchase::where('id',$product_id) -> where('user_id',$user_id) -> first();
                
                // Check if the purchase exists
                if($purchase){
                    // Delete the purchase
                    $purchase -> delete();
                    return response() -> json(['message' => 'Purchase deleted successfully'], 200);
                } else {
                    return response() -> json(['message' => 'Purchase not found'], 404);
                }
            }
        }
    }
    
}
