<?php

namespace App\Http\Requests\PurchaseRequest;

use App\Models\ApproverSettings;
use Illuminate\Foundation\Http\FormRequest;

class AssetRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            "*.pr_number" => [
                $this->route()->pr_transaction
                    ? "unique:pr_transactions,pr_number," .
                        $this->route()->pr_transaction
                    : "unique:pr_transactions,pr_number",
            ],
            // "supplier_id" => "exists:suppliers,id,deleted_at,NULL",
            "*.company_id" => "exists:companies,id,deleted_at,NULL",
            "*.business_unit_id" => "exists:business_units,id,deleted_at,NULL",
            "*.department_id" => "exists:departments,id,deleted_at,NULL",
            "*.department_unit_id" =>
                "exists:department_units,id,deleted_at,NULL",
            "*.sub_unit_id" => "exists:sub_units,id,deleted_at,NULL",
            "*.location_id" => "exists:locations,id,deleted_at,NULL",
        ];
    }
    // public function withValidator($validator)
    // {
    //     $validator->after(function ($validator) {
    //         // $validator->errors()->add("custom", $this->user()->id);
    //         // $validator->errors()->add("custom", $this->route()->id);
    //         // $validator->errors()->add("custom", "STOP!");

    //         $approvers = ApproverSettings::where(
    //             "business_unit_id",
    //             $this->input("*.business_unit_id")
    //         )
    //             ->where("company_id", $this->input("*.company_id"))
    //             ->where("department_id", $this->input("*.department_id"))
    //             ->where(
    //                 "department_unit_id",
    //                 $this->input("*.department_unit_id")
    //             )
    //             ->where("sub_unit_id", $this->input("*.sub_unit_id"))
    //             ->where("location_id", $this->input("*.location_id"))
    //             ->get()
    //             ->first();
    //         if (!$approvers) {
    //             $validator->errors()->add("message", "No approvers yet.");
    //         }
    //     });
    // }
}
