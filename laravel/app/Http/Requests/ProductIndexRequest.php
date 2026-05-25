<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class ProductIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'price_min'   => ['sometimes', 'numeric', 'min:0'],
            'price_max'   => ['sometimes', 'numeric', 'min:0'],
            'in_stock'    => ['sometimes', 'boolean'],
            'per_page'    => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort_by'     => ['sometimes', 'string', 'in:price,name,created_at'],
            'sort_dir'    => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }

    public function messages(): array
    {
        return [
            'price_min.min'  => 'Минимальная цена не может быть отрицательной.',
            'price_max.min'  => 'Максимальная цена не может быть отрицательной.',
            'per_page.max'   => 'Максимум 100 товаров на страницу.',
            'sort_by.in'     => 'Сортировка возможна по полям: price, name, created_at.',
            'sort_dir.in'    => 'Направление сортировки: asc или desc.',
        ];
    }

    /**
     * При ошибке валидации возвращаем JSON, а не редирект.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Ошибка валидации параметров.',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
