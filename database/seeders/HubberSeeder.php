<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HubberSeeder extends Seeder
{
    public function run()
    {
        // Seed Achievements
        DB::table('achievements')->insert([
            [
                'name' => 'Top Rated Driver',
                'description' => 'Maintain an average rating of 4.8 or higher',
                'icon_class' => 'fas fa-medal',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Eco Champion',
                'description' => 'Complete 50 rides in an eco-friendly vehicle',
                'icon_class' => 'fas fa-leaf',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Safe Driver',
                'description' => 'Complete 100 rides with zero safety incidents',
                'icon_class' => 'fas fa-shield-alt',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Veteran Driver',
                'description' => 'Complete 500 rides on the platform',
                'icon_class' => 'fas fa-crown',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed FAQs
        DB::table('faqs')->insert([
            [
                'question' => 'How do I book a ride?',
                'answer' => 'Simply browse available rides, select your preferred option, and complete the booking process through our secure payment system.',
                'category' => 'General',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'What payment methods do you accept?',
                'answer' => 'We accept all major credit cards, digital wallets, and bank transfers. Your payment information is securely encrypted.',
                'category' => 'Payment',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'How do I become a driver?',
                'answer' => 'Sign up as a driver, upload your license and vehicle documents, complete our verification process, and start offering rides.',
                'category' => 'Driver',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'Can I cancel my booking?',
                'answer' => 'Yes, you can cancel your booking up to 2 hours before the departure time. Cancellation fees may apply.',
                'category' => 'Booking',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'question' => 'Is my personal information safe?',
                'answer' => 'Absolutely. We use industry-standard encryption and security measures to protect your personal and payment information.',
                'category' => 'Security',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed Testimonials
        DB::table('testimonials')->insert([
            [
                'author_name' => 'Sarah M.',
                'author_role' => 'Regular Passenger',
                'text' => 'Hubber has revolutionized my daily commute. The drivers are professional, the cars are clean, and the prices are unbeatable!',
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'author_name' => 'John D.',
                'author_role' => 'Business Traveler',
                'text' => 'As someone who travels frequently for work, Hubber provides reliable and comfortable transportation every time.',
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'author_name' => 'Maria L.',
                'author_role' => 'Student',
                'text' => 'The shared rides are perfect for my budget, and I\'ve made great connections with fellow passengers.',
                'is_featured' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'author_name' => 'David R.',
                'author_role' => 'Professional Driver',
                'text' => 'Driving with Hubber has given me flexible income and the opportunity to meet interesting people every day.',
                'is_featured' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
} 