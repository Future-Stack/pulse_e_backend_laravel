<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CommunityPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CommunityPostController extends Controller
{
    /**
     * GET /api/community/posts
     * List approved posts, newest first, paginated. Optional filters: tag, life_journey_id.
     */
    public function index(Request $request)
    {
        $query = CommunityPost::query()
            ->where('is_approved', true)
            ->with(['user:id,full_name', 'lifeJourneys'])
            ->withCount(['likes', 'comments'])
            ->latest('posted_at');

        if ($request->filled('life_journey_id')) {
            $query->whereHas('lifeJourneys', function ($q) use ($request) {
                $q->where('life_journeys.id', $request->integer('life_journey_id'));
            });
        }

        if ($request->filled('tag')) {
            $query->whereJsonContains('tags', $request->string('tag')->value());
        }

        $posts = $query->paginate($request->integer('per_page', 15));

        $userId = Auth::id();

        $posts->getCollection()->transform(function ($post) use ($userId) {
            $post->is_liked = $post->isLikedBy($userId);
            // Hide author info when the post was made anonymously
            if ($post->is_anonymous) {
                $post->setRelation('user', null);
            }
            return $post;
        });

        return response()->json($posts);
    }

    /**
     * POST /api/community/posts
     */
    public function store(Request $request)
    {
        // Accept either a single id ("life_journey_id": 1) or an array
        // ("life_journey_id": [1, 2, 3]) - normalize to an array either way.
        $request->merge([
            'life_journey_id' => is_array($request->life_journey_id)
                ? $request->life_journey_id
                : [$request->life_journey_id],
        ]);

        $validated = $request->validate([
            'life_journey_id'   => 'required|array|min:1',
            'life_journey_id.*' => 'integer|exists:life_journeys,id|distinct',
            'title'             => 'nullable|string|max:255',
            'content'           => 'required|string',
            'is_anonymous'      => 'boolean',
            'tags'              => 'nullable|array',
            'tags.*'            => 'string|max:50',
        ]);

        $post = CommunityPost::create([
            'user_id'      => Auth::id(),
            'title'        => $validated['title'] ?? null,
            'slug'         => CommunityPost::generateUniqueSlug($validated['title'] ?? 'post-' . uniqid()),
            'content'      => $validated['content'],
            'is_anonymous' => $validated['is_anonymous'] ?? true,
            'is_approved'  => true, // flip to false here if posts need moderation before going live
            'tags'         => $validated['tags'] ?? [],
            'posted_at'    => now(),
        ]);

        // THIS WAS MISSING — without it, nothing ever gets written to the
        // community_post_life_journey pivot table.
        $post->lifeJourneys()->attach($validated['life_journey_id']);

        return response()->json([
            'message' => 'Post created successfully.',
            'post'    => $post->load('lifeJourneys'),
        ], 201);
    }

    /**
     * GET /api/community/posts/{slug}
     */
    public function show(CommunityPost $post)
    {
        if (!$post->is_approved) {
            return response()->json(['message' => 'Post not found or not approved.'], 404);
        }

        $post->load([
            'lifeJourneys',
            'comments' => fn ($q) => $q->latest()->with('user:id,full_name'),
        ])->loadCount(['likes', 'comments']);

        $post->is_liked = $post->isLikedBy(Auth::id());

        if ($post->is_anonymous) {
            $post->setRelation('user', null);
        } else {
            $post->load('user:id,full_name');
        }

        return response()->json($post);
    }


    public function update(Request $request, CommunityPost $post)
    {
        if ($post->user_id !== Auth::id()) {
            return response()->json(['message' => 'You are not allowed to edit this post.'], 403);
        }

        $validated = $request->validate([
            'title'              => 'nullable|string|max:255',
            'content'            => 'sometimes|required|string',
            'is_anonymous'       => 'boolean',
            'tags'               => 'nullable|array',
            'tags.*'             => 'string|max:50',
            
            'life_journey_ids'   => 'sometimes|array|min:1',
            'life_journey_ids.*' => 'integer|exists:life_journeys,id|distinct',
        ]);

        $post->update(collect($validated)->except('life_journey_ids')->all());

        if (array_key_exists('life_journey_ids', $validated)) {
            $post->lifeJourneys()->sync($validated['life_journey_ids']);
        }

        return response()->json([
            'message' => 'Post updated successfully.',
            'post'    => $post->fresh('lifeJourneys'),
        ]);
    }

    public function destroy(CommunityPost $post)
    {
        $user = Auth::user();

        if ($post->user_id !== $user->id && !($user->hasRole('admin') ?? false)) {
            return response()->json(['message' => 'You are not allowed to delete this post.'], 403);
        }

        $post->delete();

        return response()->json(['message' => 'Post deleted successfully.']);
    }


    public function approve(CommunityPost $post)
    {
        $user = Auth::user();

        if (!($user->hasRole('admin') ?? false)) {
            return response()->json([
                'message' => 'Unauthorized. Only admins can approve posts.'
            ], 403);
        }

        $post->update([
            'is_approved' => true,
            'posted_at'   => now()
        ]);

        return response()->json([
            'message' => 'Post approved successfully.',
            'post'    => $post
        ], 200);
    }

   
    public function decline(CommunityPost $post)
    {
        $user = Auth::user();

        if ($user->user_type !== 'admin') {
            return response()->json([
                'message' => 'Unauthorized. Only admins can decline posts.'
            ], 403);
        }

        $post->update([
            'is_approved' => false
        ]);

        return response()->json([
            'message' => 'Post declined successfully.'
        ]);
    }
}