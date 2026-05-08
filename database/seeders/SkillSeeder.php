<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SkillSeeder extends Seeder
{
    public function run(): void
    {
        $skillsByCategory = [
            'software-development' => [
                'Vue.js', 'React', 'Angular', 'Laravel', 'Node.js',
                'TypeScript', 'Python', 'Java', 'Go', 'Rust',
                'MySQL', 'PostgreSQL', 'MongoDB', 'Redis', 'Docker',
                'Kubernetes', 'AWS', 'Azure', 'GCP', 'Terraform',
                'Git', 'CI/CD', 'GraphQL', 'REST API', 'Microservices',
                'TDD', 'Agile', 'Scrum', 'Jira', 'Confluence',
            ],
            'ui-ux-design' => [
                'Figma', 'Sketch', 'Adobe XD', 'Photoshop', 'Illustrator',
                'Wireframing', 'Prototyping', 'User Research', 'Usability Testing',
                'Design Systems', 'Accessibility', 'Motion Design', 'After Effects',
            ],
            'product-management' => [
                'Roadmapping', 'A/B Testing', 'OKRs', 'KPIs', 'Market Research',
                'Competitive Analysis', 'Stakeholder Management', 'Product Analytics',
                'MVP', 'Growth Hacking', 'Customer Discovery',
            ],
            'digital-marketing' => [
                'SEO', 'SEM', 'Google Ads', 'Facebook Ads', 'Content Marketing',
                'Social Media Marketing', 'Email Marketing', 'Affiliate Marketing',
                'Influencer Marketing', 'Marketing Automation', 'HubSpot', 'Salesforce',
            ],
            'data-analytics' => [
                'SQL', 'R', 'Tableau', 'Power BI',
                'Machine Learning', 'Deep Learning', 'NLP', 'Computer Vision',
                'Big Data', 'Spark', 'Hadoop', 'TensorFlow', 'PyTorch',
                'Statistics', 'Data Visualization',
            ],
            'finance-accounting' => [
                'Financial Modeling', 'Valuation', 'Budgeting', 'Forecasting',
                'Auditing', 'Taxation', 'Risk Management', 'Compliance',
                'QuickBooks', 'SAP', 'Excel', 'VBA', 'Investment Analysis',
            ],
            'human-resources' => [
                'Recruiting', 'Talent Acquisition', 'Onboarding', 'Performance Management',
                'Employee Relations', 'Compensation & Benefits', 'HRIS',
                'Workday', 'BambooHR', 'Labor Law', 'Diversity & Inclusion',
            ],
            'customer-support' => [
                'Zendesk', 'Freshdesk', 'Intercom', 'Live Chat', 'CRM',
                'Ticketing Systems', 'Technical Support', 'Customer Success',
                'NPS', 'CSAT', 'Conflict Resolution', 'Active Listening',
            ],
        ];

        foreach ($skillsByCategory as $categorySlug => $skills) {
            $category = Category::where('slug', $categorySlug)->first();

            if (! $category) {
                continue;
            }

            foreach ($skills as $skillName) {
                Skill::firstOrCreate(
                    ['name' => $skillName],
                    [
                        'slug' => Str::slug($skillName),
                        'category_id' => $category->id,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
