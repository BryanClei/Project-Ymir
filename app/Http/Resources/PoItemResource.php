<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PoItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "pr_id" => $this->pr_id,
            "po_id" => $this->po_id,
            "pr_item_id" => $this->pr_item_id,
            "item" => [
                "id" => $this->item_id,
                "name" => $this->item_name,
                "code" => $this->item_code,
            ],
            "uom" => $this->uom_id,
            "price" => $this->price,
            "quantity" => $this->quantity,
            "quantity_serve" => $this->quantity_serve,
            "total_price" => $this->total_price,
            "supplier_id" => [
                "id" => $this->supplier->id,
                "name" => $this->supplier->name,
                "code" => $this->supplier->code,
            ],
            "attachments" => $this->attachment,
            "buyer_id" => $this->buyer_id,
            "buyer_name" => $this->buyer_name,
            "remarks" => $this->remarks,
            "updated_at" => $this->updated_at,
        ];
    }
}
