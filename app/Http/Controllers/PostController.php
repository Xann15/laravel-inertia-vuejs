<?php

namespace App\Http\Controllers;
use App\Models\Post;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }


    public function index()
    {
        // simple pagination + eager load
        $posts = Post::with('user')->latest()->paginate(10);
        return Inertia::render('Posts/Index', [
            'posts' => $posts
        ]);
    }


    public function create()
    {
        return Inertia::render('Posts/Create');
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);


        $data['user_id'] = Auth::id();


        Post::create($data);


        return redirect()->route('posts.index')->with('success', 'Post dibuat');
    }


    public function show(Post $post)
    {
        $post->load('user');
        
        return Inertia::render('Posts/Show', ['post' => $post]);
    }


    public function edit(Post $post)
    {
        $this->authorize('update', $post);
        return Inertia::render('Posts/Edit', ['post' => $post]);
    }


    public function update(Request $request, Post $post)
    {
        $this->authorize('update', $post);


        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);


        $post->update($data);


        return redirect()->route('posts.index')->with('success', 'Post diperbarui');
    }


    public function destroy(Post $post)
    {
        $this->authorize('delete', $post);
        $post->delete();
        return redirect()->route('posts.index')->with('success', 'Post dihapus');
    }
}
