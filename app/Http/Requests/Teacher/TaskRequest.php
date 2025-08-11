<?php

namespace App\Http\Requests\Teacher;

use Illuminate\Foundation\Http\FormRequest;

class TaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['teacher', 'company_admin', 'system_admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'language_id' => 'required|exists:languages,id',
            'company_id' => 'nullable|exists:companies,id',
            'parent_task_id' => 'nullable|exists:tasks,id',
            'category_major' => 'required|string|max:255',
            'category_minor' => 'nullable|string|max:255',
            'title' => 'required|string|max:500',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'estimated_hours' => 'required|integer|min:1|max:999',
            'points' => 'required|integer|min:1|max:1000',
            'difficulty' => 'required|in:beginner,intermediate,advanced,expert',
            'display_order' => 'nullable|integer|min:0',
            'is_required' => 'boolean',
            'is_template' => 'boolean',
            'is_active' => 'boolean',
            'available_from' => 'nullable|date',
            'available_until' => 'nullable|date|after:available_from',
            'tags' => 'nullable|string',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'exists:tasks,id',
        ];

        // ファイルアップロード（新規作成時は必須、更新時は任意）
        if ($this->isMethod('post')) {
            $rules['file'] = 'required|file|mimes:zip,pdf,doc,docx,xls,xlsx|max:102400'; // 100MB
        } else {
            $rules['file'] = 'nullable|file|mimes:zip,pdf,doc,docx,xls,xlsx|max:102400';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'language_id.required' => '言語を選択してください。',
            'language_id.exists' => '選択された言語が無効です。',
            'category_major.required' => '大区分を入力してください。',
            'title.required' => 'タイトルを入力してください。',
            'title.max' => 'タイトルは500文字以内で入力してください。',
            'estimated_hours.required' => '推定時間を入力してください。',
            'estimated_hours.min' => '推定時間は1時間以上で入力してください。',
            'points.required' => 'ポイントを入力してください。',
            'difficulty.required' => '難易度を選択してください。',
            'file.required' => '課題ファイルをアップロードしてください。',
            'file.mimes' => '許可されていないファイル形式です。',
            'file.max' => 'ファイルサイズは100MB以下にしてください。',
            'available_until.after' => '公開終了日は公開開始日より後の日付を指定してください。',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'language_id' => '言語',
            'company_id' => '企業',
            'parent_task_id' => '親課題',
            'category_major' => '大区分',
            'category_minor' => '小区分',
            'title' => 'タイトル',
            'description' => '説明',
            'instructions' => '実施手順',
            'prerequisites' => '前提条件',
            'estimated_hours' => '推定時間',
            'points' => 'ポイント',
            'difficulty' => '難易度',
            'display_order' => '表示順',
            'is_required' => '必須フラグ',
            'is_template' => 'テンプレートフラグ',
            'is_active' => '有効フラグ',
            'available_from' => '公開開始日',
            'available_until' => '公開終了日',
            'tags' => 'タグ',
            'file' => '課題ファイル',
            'dependencies' => '依存関係',
        ];
    }
}