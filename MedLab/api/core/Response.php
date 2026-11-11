<?php

/**
 * Respuestas JSON estándar para toda la API.
 */
class Response
{
    static function success($message, $data, $code, $otherData = null)
    {
        header("Content-Type: application/json; charset=utf-8");
        $response = [
            "code" => $code,
            "message" => $message,
            "data" => $data,
        ];
        if ($otherData) {
            foreach ($otherData as $key => $value) {
                $response[$key] = $value;
            }
        }
        exit(json_encode($response));
    }

    static function error($message, $code, $codeMethod)
    {
        header("Content-Type: application/json; charset=utf-8");
        http_response_code($codeMethod);
        exit(json_encode([
            "code" => $code,
            "message" => $message
        ]));
    }

}
