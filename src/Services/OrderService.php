<?php
namespace Moontec\Services;

use Carbon\Carbon;
use Moontec\Utils\NumberUtils;
use Illuminate\Support\Facades\DB;

class OrderService {
    public function getViewOrderById($id) {
        $products = array();
        $totalProducts = 0;

        $dataOrder = DB::table("orders as O")
            ->select(
                "*",
                
                "O.update_at as order_update_at",
                "O.total as order_total",
                "O.subtotal as order_subtotal",
                "O.id as order_id",                
                "O.create_at as order_create",
                "O.quantity as order_quantity",

                "OST.name as order_status",
                "OST.id as status_id",

                "OP.quantity as product_quantity",
                "OP.total as product_order_total",

                "P.name as product_name",

                "US.name as user_name",
                "US.name as user_cpf",

                "MP.name as payment_name"
            )
            ->join("order_shipping as OS", "O.shipping_id", "=", "OS.id")
            ->join("order_products as OP", "O.id", "=", "OP.order_id")
            ->join("order_status as OST", "O.status", "=", "OST.id")
            ->join("method_payment as MP", "MP.id", "=", "O.method_payment_id")
            ->join("products as P", "P.id", "=", "OP.product_id")
            ->join("users as US", "US.id", "=", "O.user_id")
            ->where("O.id", "=", $id)
            ->get()
            ->toArray();

        foreach ($dataOrder as $data) {
            array_push($products, (object)[
                "product_id" => $data->product_id,
                "quantity" => $data->product_quantity,
                "price" => NumberUtils::formatCurrency($data->product_order_total / $data->product_quantity),
                "total_price" => NumberUtils::formatCurrency($data->product_order_total),
                "name" => $data->product_name,
                "img_list" => $data->img_prod,
                "variable_name" => $data->variable_name,
                "stock_name" => $data->stock_name,
                "variable_cod" => $data->variable_cod,
                "stock_cod" => $data->stock_cod,
                "phrase" => $data->phrase
            ]);

            $totalProducts += $data->product_quantity;
        }

        $total = $dataOrder[0]->order_total - $dataOrder[0]->total_shipping - $dataOrder[0]->total_fees;

        $order = (object)[
            "id" => $dataOrder[0]->order_id,
            "user_name" => $dataOrder[0]->user_name,
            "user_cpf" => $dataOrder[0]->user_cpf,
            "total" => NumberUtils::formatCurrency($dataOrder[0]->order_total),
            "subtotal" => NumberUtils::formatCurrency($dataOrder[0]->order_subtotal),
            "discont" => NumberUtils::formatCurrency($dataOrder[0]->order_subtotal - $total),
            "status" => (object)[
                "name" => $dataOrder[0]->order_status,
                "color" => $dataOrder[0]->color,
                "id" => $dataOrder[0]->status_id
            ],
            "create_at" => Carbon::parse($dataOrder[0]->order_create)->format("d/m/Y"),
            "update_at" => $dataOrder[0]->order_update_at,
            "tracking" => $dataOrder[0]->tracking,
            "total_shipping" => NumberUtils::formatCurrency($dataOrder[0]->total_shipping),
            "nf_path" => $dataOrder[0]->nf_path,
            "method_payment_id" => $dataOrder[0]->method_payment_id,
            "payment_name" => $dataOrder[0]->payment_name,
            "quantity" => $dataOrder[0]->order_quantity,       
            "address" => $dataOrder[0]->address . ", " . $dataOrder[0]->addressNumber . ", " . $dataOrder[0]->neighborhood,
            "addressCity" => $dataOrder[0]->city . ", " . $dataOrder[0]->state . " - " . $dataOrder[0]->cep,
            "type_shipping" => $dataOrder[0]->type_shipping,
            "tracking_shipping" => $dataOrder[0]->service_code . " - ". $dataOrder[0]->carrier ." - ". $dataOrder[0]->type_shipping . " " . NumberUtils::formatCurrency($dataOrder[0]->total_shipping),
            "complement" => $dataOrder[0]->complement,
            "totalProducts" => $totalProducts,            
        ];

        if($dataOrder[0]->method_payment_id == 1) {
            $order -> installment = $dataOrder[0]->instalments . " x " . NumberUtils::formatCurrency($dataOrder[0]->order_total / $dataOrder[0]->instalments);
        }else if($dataOrder[0]->method_payment_id == 2 && $dataOrder[0]->status_id == 2) {        
            $order -> pix = DB::table("order_pix")->where("order_id", "=", $id)->first();
        }

        if ($order->status->name == "Finalizado") {
            $order->shipping = "Pedido entrege no dia " . Carbon::parse($dataOrder[0]->date_conclusion)->format("d/m/Y");
        } else if ($order->status->name == "Cancelado") {
            $order->shipping = "Entrega cancelada";
        } else if ($order->status->name == "Pendente de envio") {
            $order->shipping = "Entrega prevista para o dia " . Carbon::parse($dataOrder[0]->date_previous)->format("d/m/Y");
        }    
        
        return (object) [
            "order" => $order,            
            "products" => $products
        ];
    }
}