<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Language;

class LanguageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $languages = [
            [
                'name' => 'PHP',
                'code' => 'php',
                'version' => '8.2',
                'description' => 'PHPは、Web開発に特化したサーバーサイドスクリプト言語です。',
                'icon' => 'fab fa-php',
                'color_code' => '#777BB4',
                'display_order' => 1,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Laravel', 'Symfony', 'CodeIgniter'],
                    'difficulty' => 'intermediate',
                ]
            ],
            [
                'name' => 'JavaScript',
                'code' => 'javascript',
                'version' => 'ES2023',
                'description' => 'JavaScriptは、Webブラウザで動作するプログラミング言語です。',
                'icon' => 'fab fa-js',
                'color_code' => '#F7DF1E',
                'display_order' => 2,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['React', 'Vue.js', 'Angular', 'Node.js'],
                    'difficulty' => 'beginner',
                ]
            ],
            [
                'name' => 'Python',
                'code' => 'python',
                'version' => '3.11',
                'description' => 'Pythonは、シンプルで読みやすい汎用プログラミング言語です。',
                'icon' => 'fab fa-python',
                'color_code' => '#3776AB',
                'display_order' => 3,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Django', 'Flask', 'FastAPI'],
                    'difficulty' => 'beginner',
                ]
            ],
            [
                'name' => 'Java',
                'code' => 'java',
                'version' => '17',
                'description' => 'Javaは、オブジェクト指向プログラミング言語です。',
                'icon' => 'fab fa-java',
                'color_code' => '#007396',
                'display_order' => 4,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Spring', 'Spring Boot', 'Struts'],
                    'difficulty' => 'intermediate',
                ]
            ],
            [
                'name' => 'C#',
                'code' => 'csharp',
                'version' => '11',
                'description' => 'C#は、Microsoftが開発したオブジェクト指向プログラミング言語です。',
                'icon' => 'devicon-csharp-plain',
                'color_code' => '#239120',
                'display_order' => 5,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['.NET Core', 'ASP.NET', 'Unity'],
                    'difficulty' => 'intermediate',
                ]
            ],
            [
                'name' => 'Ruby',
                'code' => 'ruby',
                'version' => '3.2',
                'description' => 'Rubyは、まつもとゆきひろ氏が開発したプログラミング言語です。',
                'icon' => 'devicon-ruby-plain',
                'color_code' => '#CC342D',
                'display_order' => 6,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Ruby on Rails', 'Sinatra'],
                    'difficulty' => 'intermediate',
                ]
            ],
            [
                'name' => 'Go',
                'code' => 'go',
                'version' => '1.21',
                'description' => 'Goは、Googleが開発したプログラミング言語です。',
                'icon' => 'devicon-go-plain',
                'color_code' => '#00ADD8',
                'display_order' => 7,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Gin', 'Echo', 'Fiber'],
                    'difficulty' => 'advanced',
                ]
            ],
            [
                'name' => 'TypeScript',
                'code' => 'typescript',
                'version' => '5.2',
                'description' => 'TypeScriptは、JavaScriptに型システムを追加した言語です。',
                'icon' => 'devicon-typescript-plain',
                'color_code' => '#3178C6',
                'display_order' => 8,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Angular', 'NestJS', 'Next.js'],
                    'difficulty' => 'intermediate',
                ]
            ],
            [
                'name' => 'Swift',
                'code' => 'swift',
                'version' => '5.9',
                'description' => 'Swiftは、Appleが開発したiOS/macOS向けプログラミング言語です。',
                'icon' => 'devicon-swift-plain',
                'color_code' => '#FA7343',
                'display_order' => 9,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['SwiftUI', 'UIKit', 'Vapor'],
                    'difficulty' => 'advanced',
                ]
            ],
            [
                'name' => 'Kotlin',
                'code' => 'kotlin',
                'version' => '1.9',
                'description' => 'Kotlinは、JetBrainsが開発したJVM言語です。',
                'icon' => 'devicon-kotlin-plain',
                'color_code' => '#7F52FF',
                'display_order' => 10,
                'is_active' => true,
                'metadata' => [
                    'frameworks' => ['Spring Boot', 'Ktor', 'Android'],
                    'difficulty' => 'intermediate',
                ]
            ],
        ];

        foreach ($languages as $language) {
            Language::create($language);
        }
    }
}