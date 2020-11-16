<?php

namespace App\Http\Controllers;


use App\Product;
use Illuminate\Http\Request;
use App\Category;
use App\Country;
use App\User;
use App\Order;
use App\OrdersProduct;
use App\DeliveryAddress;
use App\ProductsAttribute;
use App\ProductsImage;
use Dompdf\Dompdf;
use App\ProductsComment;
use Carbon\Carbon;
use Illuminate\Pagination\Paginator;
use  Illuminate\Http\Response;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Input;
use App\Exports\productsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Arr;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductsController extends Controller
{
    public function addProduct(Request $request){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        if($request->isMethod('post')){
			$data = $request->all();
			//echo "<pre>"; print_r($data); die;

			$product = new Product;
			$product->category_id = $data['category_id'];
			$product->product_name = $data['product_name'];
			$product->product_code = $data['product_code'];
			$product->product_color = $data['product_color'];

			if(!empty($data['description'])){
				$product->description = $data['description'];
			}else{
				$product->description = '';
            }
            if(!empty($data['care'])){
                $product->care = $data['care'];
            }else{
                $product->care = '';
            }
            if(empty($data['feature_item'])){
                $feature_item='0';
            }else{
                $feature_item='1';
            }
            if(empty($data['status'])){
                $status='0';
            }else{
                $status='1';
            }


			$product->price = $data['price'];

            $this->validate($request, [
                'image'  => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
                'video' => 'mimes:mp4,ogx,oga,ogv,ogg,webm| max:20000',
                ]);

           	// Upload Image
               if($request->hasFile('image')){
                ini_set('memory_limit','256M');
            	$image_tmp = $request->file('image');
                if ($image_tmp->isValid()) {
                    // Upload Images after Resize
                    $extension = $image_tmp->getClientOriginalExtension();
	                $fileName = rand(111,99999).'.'.$extension;
                    $large_image_path = 'images/backend_images/product/large'.'/'.$fileName;
                    $medium_image_path = 'images/backend_images/product/medium'.'/'.$fileName;
                    $small_image_path = 'images/backend_images/product/small'.'/'.$fileName;

	                Image::make($image_tmp)->save($large_image_path);
 					Image::make($image_tmp)->resize(600, 600)->save($medium_image_path);
     				Image::make($image_tmp)->resize(300, 300)->save($small_image_path);

                     $product->image = $fileName;


                }
            }


              // Upload Video
            if($request->hasFile('video')){
                $video_tmp = $request->file('video');
                $video_name = $video_tmp->getClientOriginalName();
                $video_path = 'videos/';
                $video_tmp->move($video_path,$video_name);
                $product->video = $video_name;
            }
            $product->feature_item = $feature_item;

            $product->status = $status;
			$product->save();
			return redirect()->back()->with('flash_message_success', 'Product has been added successfully');
        }



        $categories = Category::where(['parent_id' => 0])->get();
		$categories_drop_down = "<option value='' selected disabled>Select</option>";
		foreach($categories as $cat){
            $categories_drop_down .= "<option value='".$cat->id."'>".$cat->name."</option>";
            $sub_categories = Category::where(['parent_id' => $cat->id])->get();
			foreach($sub_categories as $sub_cat){
				$categories_drop_down .= "<option value='".$sub_cat->id."'>&nbsp;&nbsp;--&nbsp;".$sub_cat->name."</option>";
			}
        }


		return view('admin.products.add_product')->with(compact('categories_drop_down'));
    }
    public function addAttributes(Request $request, $id=null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        $productDetails = Product::with('attributes')->where(['id' => $id])->first();
        $productDetails = json_decode(json_encode($productDetails));
        /*echo "<pre>"; print_r($productDetails); die;*/

        $categoryDetails = Category::where(['id'=>$productDetails->category_id])->first();
        $category_name = $categoryDetails->name;

        if($request->isMethod('post')){
            $data = $request->all();
            //echo "<pre>"; print_r($data); die;

            foreach($data['sku'] as $key => $val){
                if(!empty($val)){
                    $attrCountSKU = ProductsAttribute::where(['sku'=>$val])->count();
                    if($attrCountSKU>0){
                        return redirect('admin/add-attributes/'.$id)->with('flash_message_error', 'SKU already exists. Please add another SKU.');
                    }
                    $attrCountSizes = ProductsAttribute::where(['product_id'=>$id,'size'=>$data['size'][$key]])->count();
                    if($attrCountSizes>0){
                        return redirect('admin/add-attributes/'.$id)->with('flash_message_error', 'Attribute already exists. Please add another Attribute.');
                    }
                    $attr = new ProductsAttribute;
                    $attr->product_id = $id;
                    $attr->sku = $val;
                    $attr->size = $data['size'][$key];
                    $attr->price = $data['price'][$key];
                    $attr->stock = $data['stock'][$key];
                    $attr->save();
                }
            }
            return redirect('admin/add-attributes/'.$id)->with('flash_message_success', 'Product Attributes has been added successfully');

        }

        $title = "Add Attributes";

        return view('admin.products.add_attributes')->with(compact('title','productDetails','category_name'));
    }
    public function addImages(Request $request, $id=null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        $productDetails = Product::where(['id' => $id])->first();

        $categoryDetails = Category::where(['id'=>$productDetails->category_id])->first();
        $category_name = $categoryDetails->name;

        if($request->isMethod('post')){
            $data = $request->all();

            if ($request->hasFile('image')) {
                $files = $request->file('image');
                foreach($files as $file){

                    // Upload Images after Resize

                    $image = new ProductsImage;
                    $extension = $file->getClientOriginalExtension();
                    $fileName = rand(111,99999).'.'.$extension;
                    $large_image_path = 'images/backend_images/product/large'.'/'.$fileName;
                    $medium_image_path = 'images/backend_images/product/medium'.'/'.$fileName;
                    $small_image_path = 'images/backend_images/product/small'.'/'.$fileName;
                    Image::make($file)->save($large_image_path);
                    Image::make($file)->resize(600, 600)->save($medium_image_path);
                    Image::make($file)->resize(300, 300)->save($small_image_path);
                    $image->image = $fileName;
                    $image->product_id = $data['product_id'];
                    $image->save();
                }
            }

            return redirect('admin/add-images/'.$id)->with('flash_message_success', 'Product Images has been added successfully');

        }

        $productImages = ProductsImage::where(['product_id' => $id])->orderBy('id','DESC')->get();

        $title = "Add Images";
        return view('admin.products.add_images')->with(compact('title','productDetails','category_name','productImages'));
    }

    public function editAttributes(Request $request, $id=null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        if($request->isMethod('post')){
            $data = $request->all();
            /*echo "<pre>"; print_r($data); die;*/
            foreach($data['idAttr'] as $key=> $attr){
                if(!empty($attr)){
                    ProductsAttribute::where(['id' => $data['idAttr'][$key]])->update(['price' => $data['price'][$key], 'stock' => $data['stock'][$key]]);
                }
            }
            return redirect('admin/add-attributes/'.$id)->with('flash_message_success', 'Product Attributes has been updated successfully');
        }
    }

    public function deleteAttribute($id = null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
      ProductsAttribute::where(['id'=>$id])->delete();
        return redirect()->back()->with('flash_message_success', 'Product Attribute has been deleted successfully');
    }

    public function editProduct(Request $request,$id=null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
		if($request->isMethod('post')){
			$data = $request->all();
			/*echo "<pre>"; print_r($data); die;*/


            $this->validate($request, [
                'image'  => 'required|image|mimes:jpg,jpeg,png,gif|max:2048',
                'video' => 'mimes:mp4,ogx,oga,ogv,ogg,webm| max:20000',
               ]);
			// Upload Image
            if($request->hasFile('image')){
            	$image_tmp = $request->file('image');
                if ($image_tmp->isValid()) {
                    // Upload Images after Resize
                    $extension = $image_tmp->getClientOriginalExtension();
	                $fileName = rand(111,99999).'.'.$extension;
                    $large_image_path = 'images/backend_images/product/large'.'/'.$fileName;
                    $medium_image_path = 'images/backend_images/product/medium'.'/'.$fileName;
                    $small_image_path = 'images/backend_images/product/small'.'/'.$fileName;

	                Image::make($image_tmp)->save($large_image_path);
 					Image::make($image_tmp)->resize(600, 600)->save($medium_image_path);
     				Image::make($image_tmp)->resize(300, 300)->save($small_image_path);

                }
            }else if(!empty($data['current_image'])){
            	$fileName = $data['current_image'];
            }else{
            	$fileName = '';
            }

    // Upload Video
    if($request->hasFile('video')){
        $video_tmp = $request->file('video');
        $video_name = $video_tmp->getClientOriginalName();
        $video_path = 'videos/';
        $video_tmp->move($video_path,$video_name);
        $videoName = $video_name;
    }else if(!empty($data['current_video'])){
        $videoName = $data['current_video'];
    }else{
        $videoName = '';
    }

            if(empty($data['feature_item'])){
                $feature_item='0';
            }else{
                $feature_item='1';
            }
            if(empty($data['status'])){
                $status='0';
            }else{
                $status='1';
            }


            if(empty($data['description'])){
            	$data['description'] = '';
            }

            if(empty($data['care'])){
                $data['care'] = '';
            }


			Product::where(['id'=>$id])->update(['feature_item'=>$feature_item,'category_id'=>$data['category_id'],'status'=>$status,'product_name'=>$data['product_name'],
				'product_code'=>$data['product_code'],'product_color'=>$data['product_color'],'description'=>$data['description'],'care'=>$data['care'],'price'=>$data['price'],'image'=>$fileName,'video'=>$videoName,]);

			return redirect()->back()->with('flash_message_success', 'Product has been edited successfully');
		}

		// Get Product Details start //
		$productDetails = Product::where(['id'=>$id])->first();
		// Get Product Details End //

		// Categories drop down start //
		$categories = Category::where(['parent_id' => 0])->get();

		$categories_drop_down = "<option value='' disabled>Select</option>";
		foreach($categories as $cat){
			if($cat->id==$productDetails->category_id){
				$selected = "selected";
			}else{
				$selected = "";
			}
			$categories_drop_down .= "<option value='".$cat->id."' ".$selected.">".$cat->name."</option>";
			$sub_categories = Category::where(['parent_id' => $cat->id])->get();
			foreach($sub_categories as $sub_cat){
				if($sub_cat->id==$productDetails->category_id){
					$selected = "selected";
				}else{
					$selected = "";
				}
				$categories_drop_down .= "<option value='".$sub_cat->id."' ".$selected.">&nbsp;&nbsp;--&nbsp;".$sub_cat->name."</option>";
			}
		}
		// Categories drop down end //


		return view('admin.products.edit_product')->with(compact('productDetails','categories_drop_down'));
    }

    public function deleteProductVideo($id){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        // Get Video Name
        $productVideo = Product::select('video')->where('id',$id)->first();

        // Get Video Path
        $video_path = 'videos/';

        // Delete Video if exists in videos folder
        if(file_exists($video_path.$productVideo->video)){
            unlink($video_path.$productVideo->video);
        }

        // Delete Video from Products table
        Product::where('id',$id)->update(['video'=>'']);

        return redirect()->back()->with('flash_message_success','Product Video has been deleted successfully');
    }


    public function deleteProductAltImage($id){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
  // Get Product Image
  $productImage = ProductsImage::where('id',$id)->first();

  // Get Product Image Paths
  $large_image_path = 'images/backend_images/product/large/';
  $medium_image_path = 'images/backend_images/product/medium/';
  $small_image_path = 'images/backend_images/product/small/';

  // Delete Large Image if not exists in Folder
  if(file_exists($large_image_path.$productImage->image)){
      unlink($large_image_path.$productImage->image);
  }

  // Delete Medium Image if not exists in Folder
  if(file_exists($medium_image_path.$productImage->image)){
      unlink($medium_image_path.$productImage->image);
  }

  // Delete Small Image if not exists in Folder
  if(file_exists($small_image_path.$productImage->image)){
      unlink($small_image_path.$productImage->image);
  }

  // Delete Image from Products Images table
  ProductsImage::where(['id'=>$id])->delete();

  return redirect()->back()->with('flash_message_success', 'Product alternate mage has been deleted successfully');
    }

	public function deleteProduct($id = null){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
        Product::where(['id'=>$id])->Delete();
        return redirect()->back()->with('flash_message_success', 'Product has been deleted successfully');
    }
    public function deleteProductImage($id){
        if(session()->get('adminDetails')['products_access']==0){
            return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
        }
		// Get Product Image
		$productImage = Product::where('id',$id)->first();

		// Get Product Image Paths
		$large_image_path = 'images/backend_images/product/large/';
		$medium_image_path = 'images/backend_images/product/medium/';
		$small_image_path = 'images/backend_images/product/small/';

		// Delete Large Image if not exists in Folder
        if(file_exists($large_image_path.$productImage->image)){
            unlink($large_image_path.$productImage->image);
        }

        // Delete Medium Image if not exists in Folder
        if(file_exists($medium_image_path.$productImage->image)){
            unlink($medium_image_path.$productImage->image);
        }

        // Delete Small Image if not exists in Folder
        if(file_exists($small_image_path.$productImage->image)){
            unlink($small_image_path.$productImage->image);
        }

        // Delete Image from Products table
        Product::where(['id'=>$id])->update(['image'=>'']);

        return redirect()->back()->with('flash_message_success', 'Product image has been deleted successfully');
	}
    public function viewProducts(Request $request){

		$products = Product::get();
		foreach($products as $key => $val){
			$category_name = Category::where(['id' => $val->category_id])->first();
			$products[$key]->category_name = $category_name->name;
		}
		$products = json_decode(json_encode($products));
		//echo "<pre>"; print_r($products); die;
		return view('admin.products.view_products')->with(compact('products'));
    }
    public function exportProducts(){
        return Excel::download(new productsExport,'products.xlsx');
    }
    public function checkout(Request $request){
        $user_id = Auth::user()->id;
        $user_email = Auth::user()->email;
        $userDetails = User::find($user_id);
        $countries = Country::get();

    //Check if Shipping Address exists
    $shippingCount = DeliveryAddress::where('user_id',$user_id)->count();
    $shippingDetails = array();
    if($shippingCount>0){
        $shippingDetails = DeliveryAddress::where('user_id',$user_id)->first();
    }
    $session_id = session()->get('session_id');
    DB::table('cart')->where(['session_id'=>$session_id])->update(['user_email'=>$user_email]);

        if($request->isMethod('post')){
            $data = $request->all();
            /*echo "<pre>"; print_r($data); die;*/
            // Return to Checkout page if any of the field is empty
            if(empty($data['billing_name']) || empty($data['billing_address']) || empty($data['billing_city']) || empty($data['billing_state']) || empty($data['billing_country']) || empty($data['billing_pincode']) || empty($data['billing_mobile']) || empty($data['shipping_name']) || empty($data['shipping_address']) || empty($data['shipping_city']) || empty($data['shipping_state']) || empty($data['shipping_country']) || empty($data['shipping_pincode']) || empty($data['shipping_mobile'])){
                    return redirect()->back()->with('flash_message_error','Please fill all fields to Checkout!');
            }



            // Update User details
            User::where('id',$user_id)->update(['name'=>$data['billing_name'],'address'=>$data['billing_address'],'city'=>$data['billing_city'],'state'=>$data['billing_state'],'pincode'=>$data['billing_pincode'],'country'=>$data['billing_country'],'mobile'=>$data['billing_mobile']]);

            if($shippingCount>0){
                // Update Shipping Address
                DeliveryAddress::where('user_id',$user_id)->update(['name'=>$data['shipping_name'],'address'=>$data['shipping_address'],'city'=>$data['shipping_city'],'state'=>$data['shipping_state'],'pincode'=>$data['shipping_pincode'],'country'=>$data['shipping_country'],'mobile'=>$data['shipping_mobile']]);
            }else{
                // Add New Shipping Address
                $shipping = new DeliveryAddress;
                $shipping->user_id = $user_id;
                $shipping->user_email = $user_email;
                $shipping->name = $data['shipping_name'];
                $shipping->address = $data['shipping_address'];
                $shipping->city = $data['shipping_city'];
                $shipping->state = $data['shipping_state'];
                $shipping->pincode = $data['shipping_pincode'];
                $shipping->country = $data['shipping_country'];
                $shipping->mobile = $data['shipping_mobile'];
                $shipping->save();
            }



            return redirect()->action('ProductsController@orderReview');
        }

        return view('products.checkout')->with(compact('userDetails','countries'));
    }

    public function orderReview(){
        $user_id = Auth::user()->id;
        $user_email = Auth::user()->email;
        $userDetails = User::where('id',$user_id)->first();
        $shippingDetails = DeliveryAddress::where('user_id',$user_id)->first();
        $shippingDetails = json_decode(json_encode($shippingDetails));
        $userCart = DB::table('cart')->where(['user_email' => $user_email])->get();

        foreach($userCart as $key => $product){
            $productDetails = Product::where('id',$product->product_id)->first();
            $userCart[$key]->image = $productDetails->image;
        }
        return view('products.order_review')->with(compact('userDetails','shippingDetails','userCart'));
    }


    public function products($slug){
    	// Show 404 Page if Category does not exists
    	$categoryCount = Category::where(['slug'=>$slug,'status'=>1])->count();
    	if($categoryCount==0){
    		abort(404);
    	}

    	$categories = Category::with('categories')->where(['parent_id' => 0])->get();

    	$categoryDetails = Category::where(['slug'=>$slug])->first();
    	if($categoryDetails->parent_id==0){
    		$subCategories = Category::where(['parent_id'=>$categoryDetails->id])->get();
    		$subCategories = json_decode(json_encode($subCategories));
    		foreach($subCategories as $subcat){
    			$cat_ids[] = $subcat->id;
    		}
    		$productsAll = Product::whereIn('products.category_id', $cat_ids)->where('products.status','1')->orderBy('products.id','Desc');
            $breadcrumb = "<a href='/'>Home</a> / <a href='".$categoryDetails->slug."'>".$categoryDetails->name."</a>";
    	}else{
    		$productsAll = Product::where(['products.category_id'=>$categoryDetails->id])->where('products.status','1')->orderBy('products.id','Desc');
            $mainCategory = Category::where('id',$categoryDetails->parent_id)->first();
            $breadcrumb = "<a href='/'>Home</a> / <a href='".$mainCategory->slug."'>".$mainCategory->name."</a> / <a href='".$categoryDetails->slug."'>".$categoryDetails->name."</a>";
    	}

        if(!empty($_GET['color'])){
            $colorArray = explode('-',$_GET['color']);
            $productsAll = $productsAll->whereIn('products.product_color',$colorArray);
        }

        if(!empty($_GET['sleeve'])){
            $sleeveArray = explode('-',$_GET['sleeve']);
            $productsAll = $productsAll->whereIn('products.sleeve',$sleeveArray);
        }

        if(!empty($_GET['pattern'])){
            $patternArray = explode('-',$_GET['pattern']);
            $productsAll = $productsAll->whereIn('products.pattern',$patternArray);
        }

        if(!empty($_GET['size'])){
            $sizeArray = explode('-',$_GET['size']);
            $productsAll = $productsAll->join('products_attributes','products_attributes.product_id','=','products.id')
            ->select('products.*','products_attributes.product_id','products_attributes.size')
            ->groupBy('products_attributes.product_id')
            ->whereIn('products_attributes.size',$sizeArray);
        }

        $productsAll = $productsAll->paginate(6);
        /*$productsAll = json_decode(json_encode($productsAll));
        echo "<pre>"; print_r($productsAll); die;*/

        /*$colorArray = array('Black','Blue','Brown','Gold','Green','Orange','Pink','Purple','Red','Silver','White','Yellow');*/

        $colorArray = Product::select('product_color')->groupBy('product_color')->get();
        $colorArray = Arr::flatten(json_decode(json_encode($colorArray),true));


        $sizesArray = ProductsAttribute::select('size')->groupBy('size')->get();
        $sizesArray = Arr::flatten(json_decode(json_encode($sizesArray),true));
        /*echo "<pre>"; print_r($sizesArray); die;*/

    	return view('products.shop')->with(compact('categories','productsAll','categoryDetails','slug','colorArray','sizesArray','breadcrumb'));
    }
public function getProduct($id){
     // Get Product Alt Images
     $productAltImages = ProductsImage::where('product_id',$id)->get();
     echo json_encode($productAltImages);

}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function product($id){
    // Show 404 Page if Product is disabled
    $productCount = Product::where(['id'=>$id,'status'=>1])->count();
    if($productCount==0){
        abort(404);
    }

        // Get Product Details
        $productDetails = Product::with('attributes')->where('id',$id)->first();
        $productDetails = json_decode(json_encode($productDetails));
        $relatedProducts = Product::where('id','!=',$id)->where(['category_id' => $productDetails->category_id])->paginate(4);

        /*foreach($relatedProducts->chunk(3) as $chunk){
            foreach($chunk as $item){
                echo $item; echo "<br>";
            }
            echo "<br><br><br>";
        }*/

     // Get Product Alt Images
     $productAltImages = ProductsImage::where('product_id',$id)->get();
        /*$productAltImages = json_decode(json_encode($productAltImages));
        echo "<pre>"; print_r($productAltImages); die;*/
        $categories = Category::with('categories')->where(['parent_id' => 0])->get();

        $categoryDetails = Category::where('id',$productDetails->category_id)->first();
        if($categoryDetails->parent_id==0){
            $breadcrumb = "<a href='/'>Home</a> / <a href='".$categoryDetails->slug."'>".$categoryDetails->name."</a> / ".$productDetails->product_name;
        }else{
            $mainCategory = Category::where('id',$categoryDetails->parent_id)->first();
            $breadcrumb = "<a style='color:#333;' href='/'>Home</a> / <a style='color:#333;' href='/products/".$mainCategory->slug."'>".$mainCategory->name."</a> / <a style='color:#333;' href='/products/".$categoryDetails->url."'>".$categoryDetails->name."</a> / ".$productDetails->product_name;
        }

        $total_stock = ProductsAttribute::where('product_id',$id)->sum('stock');
        $comments=ProductsComment::where('product_id',$id)->get();


        return view('products.detail')->with(compact('productDetails','categories','productAltImages','total_stock','relatedProducts','breadcrumb','comments'));
    }

    public function getProductPrice(Request $request){
        $data = $request->all();

        $proArr = explode("-",$data['idsize']);
        $proAttr = ProductsAttribute::where(['product_id'=>$proArr[0],'size'=>$proArr[1]])->first();
        echo $proAttr->price;
        echo "#";
        echo $proAttr->stock;

    }


    public function addtocart(Request $request){


        $data = $request->all();
        //echo "<pre>"; print_r($data); die;


            /*echo "Wish List is selected"; die;*/



   // Check Product Stock is available or not
   $product_size = explode("-",$data['size']);
   $getProductStock = ProductsAttribute::where(['product_id'=>$data['product_id'],'size'=>$product_size[1]])->first();

   if($getProductStock->stock<$data['quantity']){
       return redirect()->back()->with('flash_message_error','Required Quantity is not available!');
   }

            // Check User is logged in

            if(empty(Auth::user()->email)){
                $data['user_email'] = '';
            }else{
                $data['user_email'] = Auth::user()->email;
            }
            $session_id = session()->get('session_id');
            if(!isset($session_id)){
                $session_id = Str::random(40);
                session()->put('session_id',$session_id);
            }



            $sizeIDArr = explode('-',$data['size']);
            $product_size = $sizeIDArr[1];

            if(empty(Auth::check())){
                $countProducts = DB::table('cart')->where(['product_id' => $data['product_id'],'product_color' => $data['product_color'],'size' => $product_size,'session_id' => $session_id])->count();
                if($countProducts>0){
                    return redirect()->back()->with('flash_message_error','Product already exist in Cart!');
                }
            }else{
                $countProducts = DB::table('cart')->where(['product_id' => $data['product_id'],'product_color' => $data['product_color'],'size' => $product_size,'user_email' => $data['user_email']])->count();
                if($countProducts>0){
                    return redirect()->back()->with('flash_message_error','Product already exist in Cart!');
                }
            }


            $getSKU = ProductsAttribute::select('sku')->where(['product_id' => $data['product_id'], 'size' => $product_size])->first();

            DB::table('cart')->insert(['product_id' => $data['product_id'],'product_name' => $data['product_name'],
                'product_code' => $getSKU['sku'],'product_color' => $data['product_color'],
                'price' => $data['price'],'size' => $product_size,'quantity' => $data['quantity'],'user_email' => $data['user_email'],'session_id' => $session_id]);

            return redirect('cart')->with('flash_message_success','Product has been added in Cart!');




    }

    public function cart(){
        if(Auth::check()){
            $user_email = Auth::user()->email;
            $userCart = DB::table('cart')->where(['user_email' => $user_email])->get();
        }else{
            $session_id = session()->get('session_id');
            $userCart = DB::table('cart')->where(['session_id' => $session_id])->get();
        }
        foreach($userCart as $key => $product){
            $productDetails = Product::where('id',$product->product_id)->first();
            $userCart[$key]->image = $productDetails->image;
        }
        return view('products.cart')->with(compact('userCart'));
    }
    public function deleteCartProduct($id=null){

        DB::table('cart')->where('id',$id)->delete();
        return redirect('cart')->with('flash_message_success','Product has been deleted in Cart!');
    }


    public function updateCartQuantity($id=null,$quantity=null){
        $getProductSKU = DB::table('cart')->select('product_code','quantity')->where('id',$id)->first();
        $getProductStock = ProductsAttribute::where('sku',$getProductSKU->product_code)->first();
        $updated_quantity = $getProductSKU->quantity+$quantity;
        if($getProductStock->stock>=$updated_quantity){
            DB::table('cart')->where('id',$id)->increment('quantity',$quantity);
            return redirect('cart')->with('flash_message_success','Product Quantity has been updated in Cart!');
        }else{
            return redirect('cart')->with('flash_message_error','Required Product Quantity is not available!');
        }
    }

    public function placeOrder(Request $request){

        if($request->isMethod('post')){
            $data = $request->all();
            $user_id = Auth::user()->id;
            $user_email = Auth::user()->email;
        // Prevent Out of Stock Products from ordering
        $userCart = DB::table('cart')->where('user_email',$user_email)->get();
        foreach($userCart as $cart){

            $getAttributeCount = Product::getAttributeCount($cart->product_id,$cart->size);
            if($getAttributeCount==0){
                Product::deleteCartProduct($cart->product_id,$user_email);
                return redirect('/cart')->with('flash_message_error','One of the product is not available. Try again!');
            }

            $product_stock = Product::getProductStock($cart->product_id,$cart->size);
            if($product_stock==0){
                Product::deleteCartProduct($cart->product_id,$user_email);
                return redirect('/cart')->with('flash_message_error','Sold Out product removed from Cart. Try again!');
            }
            /*echo "Original Stock: ".$product_stock;
            echo "Demanded Stock: ".$cart->quantity; die;*/
            if($cart->quantity>$product_stock){
                return redirect('/cart')->with('flash_message_error','Reduce Product Stock and try again.');
            }

            $product_status = Product::getProductStatus($cart->product_id);
            if($product_status==0){
                Product::deleteCartProduct($cart->product_id,$user_email);
                return redirect('/cart')->with('flash_message_error','Disabled product removed from Cart. Please try again!');
            }

            $getCategoryId = Product::select('category_id')->where('id',$cart->product_id)->first();
            $category_status = Product::getCategoryStatus($getCategoryId->category_id);
            if($category_status==0){
                Product::deleteCartProduct($cart->product_id,$user_email);
                return redirect('/cart')->with('flash_message_error','One of the product category is disabled. Please try again!');
            }



        }
            // Get Shipping Address of User
            $shippingDetails = DeliveryAddress::where(['user_email' => $user_email])->first();



            $order = new Order;
            $order->user_id = $user_id;
            $order->user_email = $user_email;
            $order->name = $shippingDetails->name;
            $order->address = $shippingDetails->address;
            $order->city = $shippingDetails->city;
            $order->state = $shippingDetails->state;
            $order->pincode = $shippingDetails->pincode;
            $order->country = $shippingDetails->country;
            $order->mobile = $shippingDetails->mobile;
            $order->order_status = "New";
            $order->payment_method = $data['payment_method'];
            $order->grand_total = $data['grand_total'];

            $order->save();


            $order_id = DB::getPdo()->lastInsertId();
            $cartProducts = DB::table('cart')->where(['user_email'=>$user_email])->get();
            foreach($cartProducts as $pro){
                $cartPro = new OrdersProduct;
                $cartPro->order_id = $order_id;
                $cartPro->user_id = $user_id;
                $cartPro->product_id = $pro->product_id;
                $cartPro->product_code = $pro->product_code;
                $cartPro->product_name = $pro->product_name;
                $cartPro->product_color = $pro->product_color;
                $cartPro->product_size = $pro->size;
                $product_price = Product::getProductPrice($pro->product_id,$pro->size);
                $cartPro->product_price = $product_price;
                $cartPro->product_qty = $pro->quantity;
                $cartPro->save();
     // Reduce Stock Script Starts
     $getProductStock = ProductsAttribute::where('sku',$pro->product_code)->first();
     /*echo "Original Stock: ".$getProductStock->stock;
     echo "Stock to reduce: ".$pro->quantity;*/
     $newStock = $getProductStock->stock - $pro->quantity;
     if($newStock<0){
         $newStock = 0;
     }
    ProductsAttribute::where('sku',$pro->product_code)->update(['stock'=>$newStock]);
     // Reduce Stock Script Ends

            }
            session()->put('order_id',$order_id);
            session()->put('grand_total',$data['grand_total']);



            if($data['payment_method']=="COD"){


                $productDetails = Order::with('orders')->where('id',$order_id)->first();
                $productDetails = json_decode(json_encode($productDetails),true);
                /*echo "<pre>"; print_r($productDetails);*/ /*die;*/

                $userDetails = User::where('id',$user_id)->first();
                $userDetails = json_decode(json_encode($userDetails),true);
                /*echo "<pre>"; print_r($userDetails); die;*/
                /* Code for Order Email Start */
                $email = $user_email;
                $messageData = [
                    'email' => $email,
                    'name' => $shippingDetails->name,
                    'order_id' => $order_id,
                    'productDetails' => $productDetails,
                    'userDetails' => $userDetails
                ];
                Mail::send('emails.order',$messageData,function($message) use($email){
                    $message->to($email)->subject('Order Placed - E-com Website');
                });
                /* Code for Order Email Ends */

                // COD - Redirect user to thanks page after saving order
                return redirect('/thanks');
            }else{
                // Paypal - Redirect user to paypal page after saving order
                return redirect('/paypal');
            }


}
    }
public function viewOrderInvoice($order_id){
    if(session()->get('adminDetails')['products_access']==0){
        return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
    }
    $orderDetails = Order::with('orders')->where('id',$order_id)->first();
    $orderDetails = json_decode(json_encode($orderDetails));
    /*echo "<pre>"; print_r($orderDetails); die;*/
    $user_id = $orderDetails->user_id;
    $userDetails = User::where('id',$user_id)->first();
    /*$userDetails = json_de	slug:{
				required:true
			},code(json_encode($userDetails));
    echo "<pre>"; print_r($userDetails);*/
    return view('admin.orders.order_invoice')->with(compact('orderDetails','userDetails'));
}
public function viewPDFInvoice($order_id){
    if(session()->get('adminDetails')['orders_access']==0){
        return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
    }
    $orderDetails = Order::with('orders')->where('id',$order_id)->first();
    $orderDetails = json_decode(json_encode($orderDetails));
    /*echo "<pre>"; print_r($orderDetails); die;*/
    $user_id = $orderDetails->user_id;
    $userDetails = User::where('id',$user_id)->first();
    /*$userDetails = json_decode(json_encode($userDetails));
    echo "<pre>"; print_r($userDetails);*/

    $output = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Example 1</title>
<style>
.clearfix:after {
content: "";
display: table;
clear: both;
}

a {
color: #5D6975;
text-decoration: underline;
}

body {
position: relative;
width: 21cm;
height: 29.7cm;
margin: 0 auto;
color: #001028;
background: #FFFFFF;
font-family: Arial, sans-serif;
font-size: 12px;
font-family: Arial;
}

header {
padding: 10px 0;
margin-bottom: 30px;
}

#logo {
text-align: center;
margin-bottom: 10px;
}

#logo img {
width: 90px;
}

h1 {
border-top: 1px solid  #5D6975;
border-bottom: 1px solid  #5D6975;
color: #5D6975;
font-size: 2.4em;
line-height: 1.4em;
font-weight: normal;
text-align: center;
margin: 0 0 20px 0;
background: url(dimension.png);
}

#project {
float: left;
}

#project span {
color: #5D6975;
text-align: right;
width: 52px;
margin-right: 10px;
display: inline-block;
font-size: 0.8em;
}

#company {
float: right;
text-align: right;
}

#project div,
#company div {
white-space: nowrap;
}

table {
width: 100%;
border-collapse: collapse;
border-spacing: 0;
margin-bottom: 20px;
}

table tr:nth-child(2n-1) td {
background: #F5F5F5;
}

table th,
table td {
text-align: center;
}

table th {
padding: 5px 20px;
color: #5D6975;
border-bottom: 1px solid #C1CED9;
white-space: nowrap;
font-weight: normal;
}

table .service,
table .desc {
text-align: left;
}

table td {
padding: 20px;
text-align: right;
}

table td.service,
table td.desc {
vertical-align: top;
}

table td.unit,
table td.qty,
table td.total {
font-size: 1.2em;
}

table td.grand {
border-top: 1px solid #5D6975;;
}

#notices .notice {
color: #5D6975;
font-size: 1.2em;
}

footer {
color: #5D6975;
width: 100%;
height: 30px;
position: absolute;
bottom: 0;
border-top: 1px solid #C1CED9;
padding: 8px 0;
text-align: center;
}
</style>
</head>
<body>
<header class="clearfix">
  <div id="logo">
    <img src="images/backend_images/logo.png">
  </div>
  <h1>INVOICE '.$orderDetails->id.'</h1>
  <div id="project" class="clearfix">
    <div><span>Order ID</span> '.$orderDetails->id.'</div>
    <div><span>Order Date</span> '.$orderDetails->created_at.'</div>
    <div><span>Order Amount</span> '.$orderDetails->grand_total.'</div>
    <div><span>Order Status</span> '.$orderDetails->order_status.'</div>
    <div><span>Payment Method</span> '.$orderDetails->payment_method.'</div>
  </div>
  <div id="project" style="float:right;">
    <div><strong>Shipping Address</strong></div>
    <div>'.$orderDetails->name.'</div>
    <div>'.$orderDetails->address.'</div>
    <div>'.$orderDetails->city.', '.$orderDetails->state.'</div>
    <div>'.$orderDetails->pincode.'</div>
    <div>'.$orderDetails->country.'</div>
    <div>'.$orderDetails->mobile.'</div>
  </div>
</header>
<main>
  <table>
    <thead>
        <tr>
            <td style="width:18%"><strong>Product Code</strong></td>
            <td style="width:18%" class="text-center"><strong>Size</strong></td>
            <td style="width:18%" class="text-center"><strong>Color</strong></td>
            <td style="width:18%" class="text-center"><strong>Price</strong></td>
            <td style="width:18%" class="text-center"><strong>Qty</strong></td>
            <td style="width:18%" class="text-right"><strong>Totals</strong></td>
        </tr>
    </thead>
    <tbody>';
    $Subtotal = 0;
    foreach($orderDetails->orders as $pro){
        $output .= '<tr>
            <td class="text-left">'.$pro->product_code.'</td>
            <td class="text-center">'.$pro->product_size.'</td>
            <td class="text-center">'.$pro->product_color.'</td>
            <td class="text-center">INR '.$pro->product_price.'</td>
            <td class="text-center">'.$pro->product_qty.'</td>
            <td class="text-right">INR '.$pro->product_price * $pro->product_qty.'</td>
        </tr>';
        $Subtotal = $Subtotal + ($pro->product_price * $pro->product_qty); }
    $output .= '<tr>
        <td colspan="5">SUBTOTAL</td>
        <td class="total">INR '.$Subtotal.'</td>
      </tr>
      <tr>
        <td colspan="5">SHIPPING CHARGES (+)</td>
        <td class="total">INR '.$orderDetails->shipping_charges.'</td>
      </tr>

      <tr>
        <td colspan="5" class="grand total">GRAND TOTAL</td>
        <td class="grand total">INR '.$orderDetails->grand_total.'</td>
      </tr>
    </tbody>
  </table>
</main>
<footer>
  Invoice was created on a computer and is valid without the signature and seal.
</footer>
</body>
</html>';

// instantiate and use the dompdf class
$dompdf = new Dompdf();
$dompdf->loadHtml($output);

// (Optional) Setup the paper size and orientation
$dompdf->setPaper('A4', 'landscape');

// Render the HTML as PDF
$dompdf->render();

// Output the generated PDF to Browser
$dompdf->stream();

}


public function thanks(Request $request){
    $user_email = Auth::user()->email;
    DB::table('cart')->where('user_email',$user_email)->delete();
    return view('orders.thanks');
}

public function thanksPaypal(){
    return view('orders.thanks_paypal');
}

public function paypal(Request $request){
    $user_email = Auth::user()->email;
    DB::table('cart')->where('user_email',$user_email)->delete();
    return view('orders.paypal');
}

public function cancelPaypal(){
    return view('orders.cancel_paypal');
}

public function userOrders(){

    $user_id = Auth::user()->id;
    $orders = Order::with('orders')->where('user_id',$user_id)->orderBy('id','DESC')->get();
    /*$orders = json_decode(json_encode($orders));
    echo "<pre>"; print_r($orders); die;*/
    return view('orders.user_orders')->with(compact('orders'));
}
public function userOrderDetails($order_id){
    $user_id = Auth::user()->id;
    $orderDetails = Order::with('orders')->where('id',$order_id)->first();
    $orderDetails = json_decode(json_encode($orderDetails));
    /*echo "<pre>"; print_r($orderDetails); die;*/
    return view('orders.user_order_details')->with(compact('orderDetails'));
}

public function viewOrders(){
    if(session()->get('adminDetails')['products_access']==0){
        return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
    }
    $orders = Order::with('orders')->orderBy('id','Desc')->get();
    $orders = json_decode(json_encode($orders));
    /*echo "<pre>"; print_r($orders); die;*/
    return view('admin.orders.view_orders')->with(compact('orders'));
}

public function viewOrderDetails($order_id){
    if(session()->get('adminDetails')['products_access']==0){
        return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
    }
    $orderDetails = Order::with('orders')->where('id',$order_id)->first();
    $orderDetails = json_decode(json_encode($orderDetails));
    /*echo "<pre>"; print_r($orderDetails); die;*/
    $user_id = $orderDetails->user_id;
    $userDetails = User::where('id',$user_id)->first();
    /*$userDetails = json_decode(json_encode($userDetails));
    echo "<pre>"; print_r($userDetails);*/
    return view('admin.orders.order_details')->with(compact('orderDetails','userDetails'));
}

public function viewOrdersCharts(){
    $current_month_orders = Order::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->month)->count();
    $last_month_orders = Order::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(1))->count();
    $last_to_last_month_orders = Order::whereYear('created_at', Carbon::now()->year)->whereMonth('created_at', Carbon::now()->subMonth(2))->count();
    return view('admin.orders.view_orders_charts')->with(compact('current_month_orders','last_month_orders','last_to_last_month_orders'));
}

public function updateOrderStatus(Request $request){
    if(session()->get('adminDetails')['products_access']==0){
        return redirect('/admin/dashboard')->with('flash_message_error','You have no access for this module');
    }
    if($request->isMethod('post')){
        $data = $request->all();
        Order::where('id',$data['order_id'])->update(['order_status'=>$data['order_status']]);
        return redirect()->back()->with('flash_message_success','Order Status has been updated successfully!');
    }
}
public function searchProducts(Request $request){
    if($request->isMethod('post')){
        $data = $request->all();
        $categories = Category::with('categories')->where(['parent_id' => 0])->get();
        $search_product = $data['product'];
        /*$productsAll = Product::where('product_name','like','%'.$search_product.'%')->orwhere('product_code',$search_product)->where('status',1)->paginate();*/

        $productsAll = Product::where(function($query) use($search_product){
            $query->where('product_name','like','%'.$search_product.'%')
            ->orWhere('product_code','like','%'.$search_product.'%')
            ->orWhere('description','like','%'.$search_product.'%')
            ->orWhere('product_color','like','%'.$search_product.'%');
        })->where('status',1)->get();

        $breadcrumb = "<a href='/'>Home</a> / ".$search_product;
        if(!empty($_GET['color'])){
            $colorArray = explode('-',$_GET['color']);
            $productsAll = Product::whereIn('products.product_color',$colorArray)->get();
        }


        if(!empty($_GET['size'])){
            $sizeArray = explode('-',$_GET['size']);
            $productsAll = Product::join('products_attributes','products_attributes.product_id','=','products.id')
            ->select('products.*','products_attributes.product_id','products_attributes.size')
            ->groupBy('products_attributes.product_id')
            ->whereIn('products_attributes.size',$sizeArray)->get();
        }

        //echo "<pre>"; print_r($productsAll); die;

        /*$colorArray = array('Black','Blue','Brown','Gold','Green','Orange','Pink','Purple','Red','Silver','White','Yellow');*/

        $colorArray = Product::select('product_color')->groupBy('product_color')->get();
        $colorArray = Arr::flatten(json_decode(json_encode($colorArray),true));


        $sizesArray = ProductsAttribute::select('size')->groupBy('size')->get();
        $sizesArray = Arr::flatten(json_decode(json_encode($sizesArray),true));
        /*echo "<pre>"; print_r($sizesArray); die;*/

        return view('products.shop')->with(compact('categories','productsAll','search_product','breadcrumb','colorArray','sizesArray'));
    }
}
public function filter(Request $request){
    $data = $request->all();
    /*echo "<pre>"; print_r($data); die;*/

    $colorUrl="";
    if(!empty($data['colorFilter'])){
        foreach($data['colorFilter'] as $color){
            if(empty($colorUrl)){
                $colorUrl = "&color=".$color;
            }else{
                $colorUrl .= "-".$color;
            }
        }
    }



    $sizeUrl="";
    if(!empty($data['sizeFilter'])){
        foreach($data['sizeFilter'] as $size){
            if(empty($sizeUrl)){
                $sizeUrl = "&size=".$size;
            }else{
                $sizeUrl .= "-".$size;
            }
        }
    }

    $finalUrl = "products/".$data['slug']."?".$colorUrl.$sizeUrl;
    return redirect::to($finalUrl);
}


}
