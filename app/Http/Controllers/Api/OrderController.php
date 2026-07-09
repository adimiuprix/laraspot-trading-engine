<?php

namespace App\Http\Controllers\Api;

use App\DTOs\CancelOrderDTO;
use App\DTOs\PlaceOrderDTO;
use App\DTOs\PlaceMarketOrderDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\PlaceOrderRequest;
use App\Http\Resources\OrderResource;
use App\Services\Trading\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private OrderService $orderService
    ) {}

    /**
     * Get user's orders.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $orders = $this->orderService->getUserOrders($request->user()->id, $status);

        return OrderResource::collection($orders);
    }

    /**
     * Place a new order.
     */
    public function store(PlaceOrderRequest $request)
    {
        $orderType = $request->validated('type');

        if ($orderType === 'market') {
            $dto = PlaceMarketOrderDTO::fromRequest($request);
            $order = $this->orderService->placeMarketOrder($dto);
        } else {
            $dto = PlaceOrderDTO::fromRequest($request);
            $order = $this->orderService->placeOrder($dto);
        }

        return OrderResource::make($order);
    }

    /**
     * Cancel an order.
     */
    public function destroy(Request $request, int $orderId)
    {
        $dto = CancelOrderDTO::fromRequest($request->user(), $orderId);
        $this->orderService->cancelOrder($dto);

        return response()->json([
            'message' => 'Order cancelled successfully',
        ]);
    }
}
