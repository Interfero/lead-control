<?php

namespace App\Http\Controllers;

use App\Services\CallbackNotificationService;
use App\Services\ComplaintNotificationService;
use App\Services\OrderCityViewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderNotificationController extends Controller
{
    public function unseenCityOrdersCount(Request $request, OrderCityViewService $orderCityViewService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => $orderCityViewService->countUnseenByCityStaff($user),
            'unseen_order_ids' => $orderCityViewService->getUnseenCityOrderIds($user),
        ]);
    }

    public function unseenComplaintsCount(Request $request, ComplaintNotificationService $complaintNotificationService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => $complaintNotificationService->countUnseen($user),
            'unseen_complaint_ids' => $complaintNotificationService->getUnseenIds($user),
        ]);
    }

    public function dueCallbacksCount(Request $request, CallbackNotificationService $callbackNotificationService): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => $callbackNotificationService->countDueCallbacks($user),
            'due_callback_order_ids' => $callbackNotificationService->getDueCallbackOrderIds($user),
        ]);
    }
}
