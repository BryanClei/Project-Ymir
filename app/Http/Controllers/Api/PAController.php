<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\PurchasingAssistant\StoreRequest;
use App\Response\Message;
use App\Models\JoPoOrders;
use App\Models\LogHistory;
use App\Models\POSettings;
use App\Models\JoPoHistory;
use App\Models\PoApprovers;
use Illuminate\Http\Request;
use App\Models\POTransaction;
use App\Models\PRTransaction;
use App\Models\JOPOTransaction;
use App\Http\Requests\JODisplay;
use App\Http\Requests\PADisplay;
use App\Functions\GlobalFunction;
use App\Models\PurchaseAssistant;
use App\Http\Resources\PoResource;
use App\Http\Resources\PAResources;
use App\Models\JobOrderTransaction;
use App\Http\Controllers\Controller;
use App\Http\Requests\PRViewRequest;
use App\Http\Resources\JoPoResource;
use App\Http\Resources\PRPOResource;
use App\Models\JobOrderTransactionPA;
use App\Http\Resources\JobOrderResource;
use App\Http\Resources\PRTransactionResource;

class PAController extends Controller
{
    public function index(PADisplay $request)
    {
        $purchase_request = PurchaseAssistant::with([
            "approver_history",
            "log_history" => function ($query) {
                $query->orderBy("created_at", "desc");
            },
            "po_transaction",
            "po_transaction.order",
            "po_transaction.approver_history",
            "po_transaction.pr_approver_history",
            "po_transaction.log_history",
        ])
            ->useFilters()
            ->dynamicPaginate();

        $is_empty = $purchase_request->isEmpty();

        if ($is_empty) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }

        PRPOResource::collection($purchase_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_REQUEST_DISPLAY,
            $purchase_request
        );
    }

    public function update(Request $request, $id)
    {
        $po_transaction = POTransaction::where("id", $id)
            ->get()
            ->first();

        if (!$po_transaction) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }

        $po_transaction->update([
            "po_description" => $request->description,
            "description" => $request->description,
        ]);

        $pr_collect = new PoResource($po_transaction);

        return GlobalFunction::save(
            Message::PURCHASE_ORDER_UPDATE,
            $pr_collect
        );
    }

    public function index_jo(JODisplay $request)
    {
        $user_id = Auth()->user()->id;
        $status = $request->status;
        $job_order_request = JobOrderTransactionPA::with(
            "order",
            "approver_history",
            "log_history",
            "jo_po_transaction",
            "jo_po_transaction.jo_approver_history"
        )
            ->orderByDesc("updated_at")
            ->useFilters()
            ->dynamicPaginate();

        $is_empty = $job_order_request->isEmpty();

        if ($is_empty) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }

        JobOrderResource::collection($job_order_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_REQUEST_DISPLAY,
            $job_order_request
        );
    }

    public function view_jo(JODisplay $request, $id)
    {
        $status = $request->status;
        $user_id = Auth()->user()->id;
        $job_order_request = JobOrderTransactionPA::with([
            "order" => function ($query) use ($status) {
                $query->when($status === "for_po", function ($query) {
                    $query->whereNull("po_at");
                });
            },
            "approver_history",
            "log_history",
            "jo_po_transaction",
            "jo_po_transaction.jo_approver_history",
        ])
            ->where("id", $id)
            ->orderByDesc("updated_at")
            ->get()
            ->first();

        if (!$job_order_request) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }
        new JobOrderResource($job_order_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_REQUEST_DISPLAY,
            $job_order_request
        );
    }

    public function index_jo_po(PRViewRequest $request)
    {
        $user_id = Auth()->user()->id;
        $status = $request->status;
        $job_order_request = JOPOTransaction::with(
            "jo_po_orders",
            "jo_approver_history",
            "log_history"
        )
            ->orderByDesc("updated_at")
            ->useFilters()
            ->dynamicPaginate();

        $is_empty = $job_order_request->isEmpty();

        if ($is_empty) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }
        JoPoResource::collection($job_order_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_ORDER_DISPLAY,
            $job_order_request
        );
    }

    public function view(PADisplay $request, $id)
    {
        $status = $request->status;
        $user_id = Auth()->user()->id;

        $purchase_request = PurchaseAssistant::where("id", $id)
            ->with([
                "order" => function ($query) use ($status, $user_id) {
                    if ($status === "to_po") {
                        $query->whereNull("supplier_id")->whereNull("buyer_id");
                    } elseif ($status === "pending") {
                        $query->whereNotNull("supplier_id");
                    }
                },
            ])
            ->orderByDesc("updated_at")
            ->get()
            ->first();

        if (!$purchase_request) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }

        new PRPOResource($purchase_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_REQUEST_DISPLAY,
            $purchase_request
        );
    }

    public function viewpo(PADisplay $request, $id)
    {
        $status = $request->status;
        $user_id = Auth()->user()->id;

        $purchase_request = POTransaction::where("id", $id)
            ->with("order")
            ->orderByDesc("updated_at")
            ->get()
            ->first();

        if (!$purchase_request) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }

        new PoResource($purchase_request);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_ORDER_DISPLAY,
            $purchase_request
        );
    }

    public function update_jo(Request $request, $id)
    {
        $job_order = JOPOTransaction::where("id", $id)
            ->get()
            ->first();

        $not_found = JOPOTransaction::where("id", $id)->exists();

        $po_approvers = $job_order->jo_approver_history()->get();

        if (!$not_found) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }
        $orders = $request->order;

        $job_order->update([
            "po_description" => $request->po_description,
            "date_needed" => $request->date_needed,
            "user_id" => $request->user_id,
            "type_id" => $request->type_id,
            "type_name" => $request->type_name,
            "business_unit_id" => $request->business_unit_id,
            "business_unit_name" => $request->business_unit_name,
            "company_id" => $request->company_id,
            "company_name" => $request->company_name,
            "department_id" => $request->department_id,
            "department_name" => $request->department_name,
            "department_unit_id" => $request->department_unit_id,
            "department_unit_name" => $request->department_unit_name,
            "location_id" => $request->location_id,
            "location_name" => $request->location_name,
            "sub_unit_id" => $request->sub_unit_id,
            "sub_unit_name" => $request->sub_unit_name,
            "account_title_id" => $request->account_title_id,
            "account_title_name" => $request->account_title_name,
            "module_name" => $request->module_name,
            "total_item_price" => $request->total_item_price,
            "status" => "Pending",
            "asset" => $request->asset,
            "sgp" => $request->sgp,
            "f1" => $request->f1,
            "f2" => $request->f2,
            "layer" => "1",
            "description" => $request->description,
        ]);

        $newOrders = collect($orders)
            ->pluck("id")
            ->toArray();
        $currentOrders = JoPoOrders::where("jo_po_id", $id)
            ->get()
            ->pluck("id")
            ->toArray();

        $currentOrdersPrice = JoPoOrders::where("jo_po_id", $id)
            ->get()
            ->pluck("total_price")
            ->toArray();

        $current_total_price = array_sum($currentOrdersPrice);

        foreach ($currentOrders as $order_id) {
            if (!in_array($order_id, $newOrders)) {
                POItems::where("id", $order_id)->forceDelete();
            }
        }

        $totalPriceSum = 0;

        foreach ($orders as $index => $values) {
            $newTotalPrice = $values["quantity"] * $values["price"];
            JoPoOrders::withTrashed()->updateOrCreate(
                [
                    "id" => $values["id"] ?? null,
                ],
                [
                    "item_id" => $values["item_id"],
                    "uom_id" => $values["uom_id"],
                    "price" => $values["price"],
                    "quantity" => $values["quantity"],
                    "total_price" => $newTotalPrice,
                    "attachment" => $values["attachment"],
                    "remarks" => $values["remarks"],
                ]
            );
            $totalPriceSum += $newTotalPrice;
        }

        $po_settings = POSettings::where("company_id", $job_order->company_id)
            ->get()
            ->first();

        if ($totalPriceSum > $current_total_price) {
            $highestPriceRange = PoApprovers::max("price_range");

            if ($totalPriceSum >= $highestPriceRange) {
                foreach ($po_approvers as $po_approver) {
                    $po_approver->update([
                        "approved_at" => null,
                        "rejected_at" => null,
                    ]);
                }

                $approvers = PoApprovers::where(
                    "price_range",
                    ">=",
                    $highestPriceRange
                )
                    ->where("po_settings_id", $po_settings->company_id)
                    ->get();
                $po_approver_history = $job_order->approver_history()->first();

                foreach ($approvers as $index) {
                    $existing_approver = JoPoHistory::where(
                        "po_id",
                        $po_approver_history->po_id
                    )
                        ->where("approver_id", $index["approver_id"])
                        ->first();

                    if (!$existing_approver) {
                        JoPoHistory::create([
                            "po_id" => $po_approver_history->po_id,
                            "approver_id" => $index["approver_id"],
                            "approver_name" => $index["approver_name"],
                            "layer" => $index["layer"],
                        ]);
                    }
                }
            }
        }

        $poTransaction = $job_order->fresh([
            "jo_po_orders",
            "jo_approver_history",
        ]);

        new JoPoResource($poTransaction);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_ORDER_UPDATE,
            $poTransaction
        );
    }

    public function update_price(Request $request, $id)
    {
        $user_id = Auth()->user()->id;
        $job_order = JOPOTransaction::where("id", $id)
            ->get()
            ->first();
        $oldTotalPrice = $job_order->total_item_price;
        $not_found = JOPOTransaction::where("id", $id)->exists();

        $po_approvers = $job_order->jo_approver_history()->get();

        if (!$not_found) {
            return GlobalFunction::notFound(Message::NOT_FOUND);
        }
        $orders = $request->order;
        $newOrders = collect($orders)
            ->pluck("id")
            ->toArray();
        $currentOrders = JoPoOrders::where("jo_po_id", $newOrders)
            ->get()
            ->pluck("id")
            ->toArray();

        foreach ($currentOrders as $order_id) {
            if (!in_array($order_id, $newOrders)) {
                JoPoOrders::where("id", $order_id)->forceDelete();
            }
        }

        $updatedItems = [];
        $totalPriceSum = 0;

        foreach ($orders as $values) {
            $order_id = $values["id"];
            $poItem = JoPoOrders::where("id", $order_id)->first();

            if ($poItem) {
                $oldPrice = $poItem->unit_price;
                $newPrice = $values["price"];
                $newTotalPrice = $poItem->quantity * $values["price"];
                $poItem->update([
                    "unit_price" => $values["price"],
                    "total_price" => $newTotalPrice,
                ]);
                $totalPriceSum += $newTotalPrice;

                $updatedItems[] = [
                    "id" => $order_id,
                    "old_price" => $oldPrice,
                    "new_price" => $newPrice,
                ];
            }
        }

        $activityDescription = "Updated prices for JO PO items: ";
        foreach ($updatedItems as $item) {
            $activityDescription .= "Item ID {$item["id"]}: {$item["old_price"]} -> {$item["new_price"]}, ";
        }
        $activityDescription = rtrim($activityDescription, ", ") . ".";

        LogHistory::create([
            "activity" => $activityDescription,
            "jo_po_id" => $id,
            "action_by" => $user_id,
        ]);

        $oldTotalPrice = $job_order->total_item_price;

        if ($totalPriceSum > $oldTotalPrice) {
            foreach ($po_approvers as $po_approver) {
                $po_approver->update([
                    "approved_at" => null,
                    "rejected_at" => null,
                ]);
            }
        }

        $po_settings = POSettings::where("company_id", $job_order->company_id)
            ->get()
            ->first();

        $highestPriceRange = PoApprovers::max("price_range");

        $job_order->update([
            "total_item_price" => $totalPriceSum,
            "status" => "Pending",
            "layer" => "1",
            "updated_by" => $user_id,
            "edit_remarks" => $request->edit_remarks,
        ]);

        if ($totalPriceSum >= $highestPriceRange) {
            $approvers = PoApprovers::where(
                "price_range",
                ">=",
                $highestPriceRange
            )
                ->where("po_settings_id", $po_settings->company_id)
                ->get();
            $po_approver_history = $job_order->jo_approver_history()->first();

            foreach ($approvers as $index) {
                $existing_approver = JoPoHistory::where(
                    "jo_po_id",
                    $po_approver_history->jo_po_id
                )
                    ->where("approver_id", $index["approver_id"])
                    ->first();

                if (!$existing_approver) {
                    JoPoHistory::create([
                        "jo_po_id" => $po_approver_history->jo_po_id,
                        "approver_id" => $index["approver_id"],
                        "approver_name" => $index["approver_name"],
                        "layer" => $index["layer"],
                    ]);
                }
            }
        }

        $poTransaction = $job_order->fresh([
            "jo_po_orders",
            "jo_approver_history",
        ]);

        new JoPoResource($poTransaction);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_ORDER_UPDATE,
            $poTransaction
        );
    }

    public function tagging_badge(Request $request)
    {
        $status = $request->status;
        $for_tagging = PurchaseAssistant::with([
            "approver_history",
            "log_history" => function ($query) {
                $query->orderBy("created_at", "desc");
            },
            "po_transaction",
            "po_transaction.order",
            "po_transaction.approver_history",
        ])
            ->where("status", "Approved")
            ->whereHas("order", function ($query) {
                $query
                    ->where(function ($query) {
                        $query
                            ->whereNull("buyer_id")
                            ->orWhereNull("buyer_name")
                            ->whereNull("po_at");
                    })
                    ->whereNull("po_at");
            })
            ->with([
                "order" => function ($query) {
                    $query->whereNull("buyer_id");
                },
            ])
            ->whereNull("for_po_only")
            ->whereNull("rejected_at")
            ->whereNull("voided_at")
            ->whereNull("cancelled_at")
            ->whereHas("approver_history", function ($query) {
                $query->whereNotNull("approved_at");
            })
            ->count();
        $for_po = PurchaseAssistant::with([
            "approver_history",
            "log_history" => function ($query) {
                $query->orderBy("created_at", "desc");
            },
            "po_transaction",
            "po_transaction.order",
            "po_transaction.approver_history",
        ])
            ->where("status", "Approved")
            ->with([
                "order" => function ($query) {
                    $query->whereNull("buyer_id");
                },
            ])
            ->whereNotNull("for_po_only")
            ->whereNull("rejected_at")
            ->whereNull("voided_at")
            ->whereNull("cancelled_at")
            ->whereDoesntHave("po_transaction")
            ->count();
        $tagged_buyer = PurchaseAssistant::with([
            "order" => function ($query) {
                $query->whereNotNull("buyer_id");
            },
        ])
            ->whereHas("order", function ($query) {
                $query->whereNotNull("buyer_id")->whereNull("po_at");
            })
            ->whereDoesntHave("po_transaction")
            ->count();
        $pending_po_count = PurchaseAssistant::where("status", "Approved")
            ->with([
                "po_transaction" => function ($query) {
                    $query
                        ->where(function ($q) {
                            $q->where("status", "Pending")->orWhere(
                                "status",
                                "For Approval"
                            );
                        })
                        ->whereHas("order", function ($q) {
                            $q->whereNotNull("buyer_id");
                        });
                },
            ])
            ->where(function ($query) {
                $query
                    ->whereHas("order", function ($query) {
                        $query->whereNotNull("buyer_id");
                    })
                    ->orWhereNotNull("for_po_only");
            })
            ->whereHas("po_transaction")
            ->whereNull("rejected_at")
            ->whereNull("voided_at")
            ->whereNull("cancelled_at")
            ->withCount([
                "po_transaction" => function ($query) {
                    $query
                        ->where(function ($q) {
                            $q->where("status", "Pending")->orWhere(
                                "status",
                                "For Approval"
                            );
                        })
                        ->orWhereNotNull("for_po_only")
                        ->whereHas("order", function ($q) {
                            $q->whereNotNull("buyer_id");
                        });
                },
            ])
            ->get()
            ->sum("po_transaction_count");

        $approved = PurchaseAssistant::withCount([
            "po_transaction" => function ($query) {
                $query
                    ->whereNull("rejected_at")
                    ->whereNull("voided_at")
                    ->whereNull("cancelled_at")
                    ->where("status", "For Receiving")
                    ->whereNotNull("approved_at");
            },
        ])
            ->get()
            ->sum("po_transaction_count");

        $rejected = PurchaseAssistant::with([
            "po_transaction" => function ($query) {
                $query->whereNotNull("rejected_at");
            },
        ])
            ->whereHas("order", function ($query) {
                $query->whereNotNull("buyer_id");
            })
            ->whereHas("po_transaction", function ($query) {
                $query->where("status", "Reject");
            })
            ->count();

        $return_po = PurchaseAssistant::whereHas("order", function ($query) {
            $query->whereNull("buyer_id");
        })
            ->whereHas("po_transaction", function ($query) {
                $query->where("status", "Return");
            })
            ->with([
                "po_transaction" => function ($query) {
                    $query->where("status", "Return");
                },
                "order" => function ($query) {
                    $query->whereNull("buyer_id");
                },
            ])
            ->count();

        $cancel = PurchaseAssistant::withCount([
            "po_transaction" => function ($query) {
                $query
                    ->where("status", "Cancelled")
                    ->whereNotNull("cancelled_at");
            },
        ])
            ->get()
            ->sum("po_transaction_count");

        $result = [
            "for_tagging" => $for_tagging,
            "for_po_only" => $for_po,
            "tagged_request" => $tagged_buyer,
            "for_approval" => $pending_po_count,
            "approved" => $approved,
            "rejected" => $rejected,
            "return_po" => $return_po,
            "cancel_po" => $cancel,
        ];

        return GlobalFunction::responseFunction(
            Message::DISPLAY_COUNT,
            $result
        );
    }

    public function return_pr(Request $request, $id)
    {
        $reason = $request->reason;
        $pr_transaction = PRTransaction::where("id", $id)->first();

        $pr_transaction->update(["status" => "Return", "reason" => $reason]);

        new JoPoResource($pr_transaction);

        return GlobalFunction::responseFunction(
            Message::PURCHASE_ORDER_UPDATE,
            $pr_transaction
        );
    }
}
