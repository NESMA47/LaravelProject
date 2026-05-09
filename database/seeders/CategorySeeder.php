<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Software Development',
                'slug' => 'software-development',
                'icon' => 'Code',
                'description' => 'Frontend, backend, mobile, and full-stack roles',
                'display_order' => 1,
            ],
            [
                'name' => 'UI/UX Design',
                'slug' => 'ui-ux-design',
                'icon' => 'Palette',
                'description' => 'User interface and user experience design roles',
                'display_order' => 2,
            ],
            [
                'name' => 'Product Management',
                'slug' => 'product-management',
                'icon' => 'LayoutDashboard',
                'description' => 'Product strategy, roadmap, and lifecycle management',
                'display_order' => 3,
            ],
            [
                'name' => 'Digital Marketing',
                'slug' => 'digital-marketing',
                'icon' => 'TrendingUp',
                'description' => 'SEO, SEM, content marketing, and social media roles',
                'display_order' => 4,
            ],
            [
                'name' => 'Data & Analytics',
                'slug' => 'data-analytics',
                'icon' => 'BarChart2',
                'description' => 'Data science, analytics, and business intelligence roles',
                'display_order' => 5,
            ],
            [
                'name' => 'Finance & Accounting',
                'slug' => 'finance-accounting',
                'icon' => 'DollarSign',
                'description' => 'Financial analysis, accounting, and audit roles',
                'display_order' => 6,
            ],
            [
                'name' => 'Human Resources',
                'slug' => 'human-resources',
                'icon' => 'Users',
                'description' => 'Recruiting, talent management, and HR operations',
                'display_order' => 7,
            ],
            [
                'name' => 'Customer Support',
                'slug' => 'customer-support',
                'icon' => 'Headphones',
                'description' => 'Customer success, support, and service roles',
                'display_order' => 8,
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
