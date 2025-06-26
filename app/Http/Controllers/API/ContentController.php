<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Testimonial;
use App\Models\FAQ;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContentController extends Controller
{
    // Testimonials endpoints
    public function getTestimonials(Request $request): JsonResponse
    {
        $query = Testimonial::query();

        if ($request->featured) {
            $query->featured();
        }

        $testimonials = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $testimonials
        ]);
    }

    public function createTestimonial(Request $request): JsonResponse
    {
        $request->validate([
            'author_name' => 'required|string|max:255',
            'author_role' => 'required|string|max:255',
            'text' => 'required|string',
            'is_featured' => 'sometimes|boolean',
        ]);

        $testimonial = Testimonial::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Testimonial created successfully',
            'data' => $testimonial
        ], 201);
    }

    // FAQ endpoints
    public function getFAQs(Request $request): JsonResponse
    {
        $query = FAQ::active();

        if ($request->category) {
            $query->byCategory($request->category);
        }

        $faqs = $query->orderBy('category')->orderBy('created_at')->get();

        // Group by category
        $groupedFAQs = $faqs->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $groupedFAQs
        ]);
    }

    public function getFAQCategories(): JsonResponse
    {
        $categories = FAQ::active()
            ->select('category')
            ->distinct()
            ->pluck('category');

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    public function createFAQ(Request $request): JsonResponse
    {
        $request->validate([
            'question' => 'required|string|max:255',
            'answer' => 'required|string',
            'category' => 'required|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $faq = FAQ::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'FAQ created successfully',
            'data' => $faq
        ], 201);
    }

    public function updateFAQ(Request $request, FAQ $faq): JsonResponse
    {
        $request->validate([
            'question' => 'sometimes|string|max:255',
            'answer' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'is_active' => 'sometimes|boolean',
        ]);

        $faq->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'FAQ updated successfully',
            'data' => $faq
        ]);
    }

    public function deleteFAQ(FAQ $faq): JsonResponse
    {
        $faq->delete();

        return response()->json([
            'success' => true,
            'message' => 'FAQ deleted successfully'
        ]);
    }
} 