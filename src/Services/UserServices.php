<?php

namespace Moontec\Services;

use Moontec\Repository\UserRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserServices
{
    public function __construct(protected UserRepository $userRepository)
    {
    }

    public function updatePerfil(Request $request, $id, $updateAdmin = false)
    {        
        try {             
            if (DB::table('users')->where("email", "=", $request->get("email"))->where("id","<>", $id)->exists()) {
                throw new Exception("Ja existe um usuario registrado com esse email.");
            }               
        
            $user = [
                "email" => $request->get("email"),
                "user" => $request->get("email"),
                "cpf" => str_replace(["-", "."], "", $request->get("cpf")),
                "ddd_phone" => $request->get("dddPhone"),
                "phone" => str_replace("-", "", $request->get("phone")),                
    
                "cep" => $request->get("cep"),
                "address" => $request->get("address"),
                "addressNumber" => $request->get("addressNumber"),
                "neighborhood" => $request->get("neighborhood"),
                "city" => $request->get("city"),
                "state" => $request->get("state"),
                "complement" => $request->get("complement")
            ];

            if($updateAdmin) {
                $user["isAdmin"] = boolval($request->get("admin")) ?? 0;
            }
    
            $this->userRepository->update($id, $user);
        } catch (Exception $e) { 
            Log::channel("exception")->error($e->getMessage());
            return $e->getMessage();
        }
    }
}
