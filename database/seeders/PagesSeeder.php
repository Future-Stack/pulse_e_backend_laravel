<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Terms of Service
        Page::updateOrCreate(
            ['slug' => Str::slug('Terms of Service')],
            [
                'title' => 'Terms of Service',
                'content' => [
                    'heading' => 'Terms of Service',
                    'paragraphs' => [
                        'By using Neumera, you agree to our Terms of Service. Neumera provides health education and tracking tools. It is not a medical device, does not provide medical advice, and is not a substitute for professional healthcare. Use of the app is at your own discretion.',
                        'Neumera is a health tracking and wellness app. It is NOT a substitute for professional medical advice, diagnosis, or treatment. Always consult with qualified healthcare providers regarding your health concerns.',
                        'All content, features, and functionality of the Services, including but not limited to text, graphics, logos, icons, images, audio clips, video clips, data compilations, and software, are owned by Neumera LLC or its licensors and are protected by United States and international copyright, trademark, patent, trade secret, and other intellectual property laws.',
                        'You retain ownership of your personal data and content you submit. By using the Services, you grant us a limited license to use, store, and process your data as described in our Privacy Policy.',
                    ],
                ],
                'status' => true,
            ]
        );

        // Privacy Policy
        Page::updateOrCreate(
            ['slug' => Str::slug('Privacy Policy')],
            [
                'title' => 'Privacy Policy',
                'content' => [
                    'heading' => 'Privacy Policy',
                    'paragraphs' => [
                        'Neumera collects health data you choose to input. We do not sell your personal health data. You can request a full data export or deletion at any time under Privacy Settings.',
                        'Neumera is a general wellness platform. It is not a medical device and does not provide medical advice, diagnosis, or treatment. "Neumera," our AI coach, generates generalized wellness guidance and pattern-based insights. All outputs may be inaccurate or incomplete and should not be relied upon for urgent, emergency, or clinical decisions. If you have a medical concern, contact a licensed healthcare professional.',
                        'Fight the Number LLC operates the Neumera mobile application and website. We are committed to protecting your privacy.',
                    ],
                    'sections' => [
                        [
                            'title' => 'Data Usage',
                            'body' => 'Your health data is encrypted end-to-end and never sold to third parties. You may export or delete your data at any time.',
                        ],
                        [
                            'title' => 'AI Disclaimer',
                            'body' => 'Neumera AI provides health insights for informational purposes only. It is not a substitute for professional medical advice, diagnosis, or treatment.',
                        ],
                        [
                            'title' => 'Data Control',
                            'body' => 'You control exactly which data sources are connected. All integrations can be disconnected at any time from your Profile settings.',
                        ],
                    ],
                ],
                'status' => true,
            ]
        );

        // Medical Advice
        Page::updateOrCreate(
            ['slug' => Str::slug('Medical Advice')],
            [
                'title' => 'Medical Advice',
                'content' => [
                    'heading' => 'Medical Advice',
                    'paragraphs' => [
                        'Neumera does not provide medical advice, diagnosis, or treatment. Health insights, scores, and signals are informational only. Always consult a licensed healthcare professional before making health decisions.',
                    ],
                ],
                'status' => true,
            ]
        );

        // Disclaimer
        Page::updateOrCreate(
            ['slug' => Str::slug('Disclaimer')],
            [
                'title' => 'Disclaimer',
                'content' => [
                    'heading' => 'Disclaimer',
                    'sub_heading' => '🔴 Fertility awareness, not birth control',
                    'paragraphs' => [
                        'Neumera is not a contraceptive and has not been cleared or approved by the U.S. FDA as a method of contraception. Cycle predictions, fertile window estimates, and ovulation predictions are educational estimates only. They may be inaccurate and cannot tell you which days are safe to avoid pregnancy. Do not use Neumera to prevent pregnancy. Consult a healthcare provider for contraceptive needs.',
                    ],
                ],
                'status' => true,
            ]
        );
    }
}
