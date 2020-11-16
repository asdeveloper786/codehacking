@extends('layouts.adminLayout.admin_design')
@section('content')
@if(Session::has('flash_message_error'))

<div class="blogError" role="alert">

  </div>
@endif
@if(Session::has('flash_message_success'))

<div class="blogSuccess" role="alert">

  </div>
@endif
<div id="page-wrapper" >
    <div class="header">
                  <h1 class="page-header">
                       Blogs
                  </h1>
                  <ol class="breadcrumb">
                <li><a href="#">Home</a></li>
                <li><a href="#">Forms</a></li>
                <li class="active">Data</li>
              </ol>

  </div>

      <div id="page-inner">

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                  <div class="card-header">
                    <h5 class="title">Edit Blog</h5>
                  </div>
                  <div class="card-body">
                    <form method="POST" action="{{ url('admin/edit-blog/'.$blogDetails->id) }}" id="editBlogForm" enctype="multipart/form-data">
                        @csrf
                        <div class="row">

                        <div class="col-md-6 px-md-1">
                          <div class="form-group">
                            <label>Blog Title</label>
                            <input value="{{ $blogDetails->title }}" id="category_name" type="text" class="form-control" name="title" autocomplete="category_name">
                        </div>
                        </div>

                      </div>
                      <div class="row">
                        <div class="col-md-6 pr-md-1">
                          <div class="form-group">
                            <label>Description</label>
                            <textarea name="description" autocomplete="description" class="form-control" class="textarea_editor span12">{{ $blogDetails->description }}</textarea>
                        </div>
                        </div>

                      </div>
                      <div class="row">
                        <div class="col-md-6 pr-md-1">
                            <div class="form-group">
                              <label>URL</label>
                              <input value="{{ $blogDetails->slug }}"  id="url" type="text" class="form-control" name="slug" autocomplete="url">
                            </div>
                          </div>

                      </div>

                      <div class="row">
                        <div class="col-md-4 pr-md-1">
                          <div class="form-group">
                            <input name="status"  type="checkbox" value="1" id="test6" @if($blogDetails->status == "1") checked @endif   />
                            <label for="test6">Enable/Disable</label>
                          </div>
                        </div>

                      </div>
                      <div class="row">
                        <div class="col-md-4 pr-md-1">
                          <div class="form-group">

                            <div  value="{{ $blogDetails->image }}" id="uniform-undefined"><input  class="form-control" name="image" id="image" type="file"></div>
                            @if(!empty($blogDetails->image))
                            <input type="hidden" name="current_image" value="{{ $blogDetails->image }}">
                          @endif
                          </div>
                        </div>

                      </div>
                      <div class="row">
                        <div class="col-md-8">
                          <div class="form-group">

                                <button type="submit" class="btn btn-fill btn-primary">Edit</button>

                          </div>
                        </div>
                      </div>
                    </form>
                  </div>

                </div>
              </div>

              </div>
          <!-- /.col-lg-12 -->

@endsection
@section('scripts')
<script>
 jQuery(function(){
   //blog Error
   window.setTimeout(function() {
    $(".blogError").fadeTo(0, 0).slideUp(0, function(){
        swal({
    position: 'top-end',
  icon: 'error',
  title: '{!! session('flash_message_error') !!}',
  showConfirmButton: false,
  timer: 3000
})
    });
}, 0);
       //blog Error
       window.setTimeout(function() {
    $(".blogSuccess").fadeTo(0, 0).slideUp(0, function(){
        swal({
    position: 'top-end',
  icon: 'success',
  title: '{!! session('flash_message_success') !!}',
  showConfirmButton: false,
  timer: 3000
})
    });
}, 0);

	// Validate contact form on keyup and submit
	$("#editBlogForm").validate({
		rules:{
			title:{
				required:true,
			},
			description:{
				required:true
			},
            slug:{
				required:true
			},







		},
        messages:{


		}
	});





});
  </script>
@endsection
