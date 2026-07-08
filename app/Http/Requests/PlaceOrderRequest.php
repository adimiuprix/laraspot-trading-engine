<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlaceOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => ['required', 'string', 'regex:/^[A-Z]+\/?[A-Z]*$/'],
            'side' => ['required', 'string', 'in:buy,sell'],
            'price' => ['required', 'numeric', 'min:0.00000001'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'symbol.regex' => 'Symbol must be in format BTCUSDT or BTC/USDT',
            'side.in' => 'Side must be either buy or sell',
            'price.min' => 'Price must be greater than 0',
            'amount.min' => 'Amount must be greater than 0',
        ];
    }
}
