<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Str;
use InvalidArgumentException;
use GuzzleHttp\Client;
use Storage;
use App\User;
use Auth;

/**
 * Handles all of the log in workflow via an API.
 * 
 */
class ApiLoginController extends Controller
{   
    /**
     * Loggs in a user via API, if registered.
     * Uses the client ID of this application.
     * There will be a callback from the API.
     * 
     * @return void
     * 
     */
    function login(Request $request)
    {
        $request->session()->put('state', $state = Str::random(40));

        $query = http_build_query([
            'client_id' => 13,
            'redirect_uri' => 'http://localhost:8001/callback',
            'response_type' => 'code',
            'scope' => '',
            'state' => $state,
        ]);

        return redirect('http://localhost:8000/oauth/authorize?'.$query);
    }

    /**
     * Is called, when an API sends back the access token via a callback.
     * Saves the access token in the session.
     * Redirects to the useres dashboard.
     * 
     * @param Request
     * @return void
     * 
     */
    function convertToAccessToken(Request $request)
    {
        $http = new Client;

        $response = $http->post('http://localhost:8000/oauth/token', [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'client_id' => '13',
                'client_secret' => '7MEUafRdRz2EK7PTrrexgzYV2dOCaVt4LAtaLV2O',
                'redirect_uri' => 'http://localhost:8001/callback',
                'code' => $request->code,
            ],
        ]);
        
        $body = json_decode((string) $response->getBody(), true);
        $accessToken = $body['access_token'];

        session()->put('access_token', $accessToken);
        
        $this->loginApiUser();

        return redirect()->to('dashboard');
    }

    /**
     * Helper method for logging in a user in this application,
     * since authorizing via API will not automatically log in a user here
     * 
     * @return void
     * 
     */
    function loginApiUser()
    {
        $client = new Client;
        $accessToken = session('access_token');

        $response = $client->request('GET', 'http://localhost:8000/api/user', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$accessToken,
            ]
        ]);
        
        $conv_response= json_decode($response->getBody(), true);
        $name = $conv_response['name'];
        $email = $conv_response['email'];
        
        $userExist = User::where('email', $email)->first();

        if($userExist) {
            Auth::loginUsingId($userExist->id);
        }else {
            $user = new User;

            $user->name = $name;
            $user->email = $email;
            $user->password = md5(rand(1,10000));

            $user->save();

            Auth::loginUsingId($user->id);
        }
    }
}
