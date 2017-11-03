<?php

namespace App\Exceptions;

use App\Libraries\Utils;
use Auth;
use Exception;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Input;
use LERN;
use Request;
use Settings;
use View;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        if ($this->shouldReport($exception)) {

            //Check to see if LERN is installed otherwise you will not get an exception.
            if (app()->bound("lern")) {

                $lernRecordEnabled = Settings::get('lern.enable_record');
                $lernNotifyEnabled = Settings::get('lern.enable_notify');

                if ($lernRecordEnabled) {
                    LERN::record($exception); //Record the Exception to the database
                }

                if ($lernNotifyEnabled) {
                    $this->setLERNNotificationFormat(); // Set some formatting options
                    LERN::notify($exception); //Notify the Exception
                }

            }
        }

        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        return parent::render($request, $exception);
    }


    private function setLERNNotificationFormat()
    {
        //Change the subject
        LERN::setSubject("[" . Settings::get('lern.notify.channel') . "]: An Exception was thrown! (" . date("D M d, Y G:i", time()) . " UTC)");

        //Change the message body
        LERN::setMessage(function (Exception $exception) {
            $url = Request::url();
            $ip = Request::ip();
            $method = Request::method();
            $user = Auth::user();
            if ($user) {
                $user_id = $user->id;
                $user_name = $user->username;
                $user_first_name = $user->first_name;
                $user_last_name = $user->last_name;
            } else {
                $user_id = 'N/A';
                $user_name = 'unauthenticated';
                $user_first_name = 'Unauthenticated User';
                $user_last_name = 'N/A';
            }
            $exception_class = get_class($exception);
            $exception_file = $exception->getFile();
            $exception_line = $exception->getLine();
            $exception_message = $exception->getMessage();
            $exception_trace = $exception->getTrace();
            $input = Input::all();
            if (!empty($input)) {
                if (array_has($input, 'password')) {
                    $input['password'] = "hidden-secret";
                    $input['password_confirmation'] = "hidden-secret";
                }
                $input = json_encode($input);
            } else {
                $input = "";
            }

            $exception_trace_formatted = [];
            foreach ($exception->getTrace() as $trace) {
                $formatted_trace = "";

                if (isset($trace['function']) && isset($trace['class'])) {
                    $formatted_trace = sprintf('at %s%s%s(...)', Utils::formatClass($trace['class']), $trace['type'], $trace['function']);
                }
                else if (isset($trace['function'])) {
                    $formatted_trace = sprintf('at %s(...)', $trace['function']);
                }
                if (isset($trace['file']) && isset($trace['line'])) {
                    $formatted_trace .= Utils::formatPath($trace['file'], $trace['line']);
                }
                $exception_trace_formatted[] = $formatted_trace;
            }

            $view = View::make('emails.html.lern_notification', compact('url', 'method', 'user_id', 'user_name',
                'user_first_name', 'user_last_name', 'exception_class', 'exception_file', 'exception_line',
                'exception_message', 'exception_trace', 'exception_trace_formatted', 'input', 'ip'));

            $msg = $view->render();

            return $msg;
        });
    }
}