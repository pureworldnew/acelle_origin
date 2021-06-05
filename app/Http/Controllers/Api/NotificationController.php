<?php

namespace Acelle\Http\Controllers\Api;

use Illuminate\Http\Request;
use Acelle\Http\Controllers\Controller;

/**
 * /api/v1/plans - API controller for managing plans.
 */
class NotificationController extends Controller
{
    /**
     * Display all notifications.
     *
     * GET /api/v1/plans
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $user = \Auth::guard('api')->user();

        return \Response::json(['message' => 'Comming...'], 200);
    }
}
