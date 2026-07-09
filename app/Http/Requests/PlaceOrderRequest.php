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
            'type' => ['required', 'string', 'in:limit,market'],
            'price' => ['required_if:type,limit', 'nullable', 'numeric', 'min:0.00000001'],
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
            'type.in' => 'Type must be either limit or market',
            'price.required_if' => 'Price is required for limit orders',
            'price.min' => 'Price must be greater than 0',
            'amount.min' => 'Amount must be greater than 0',
        ];
    }
}
