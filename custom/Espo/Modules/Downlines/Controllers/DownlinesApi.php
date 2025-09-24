<?php

namespace Espo\Modules\Downlines\Controllers;

use Espo\Core\Controllers\Base;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Api\Request;

class DownlinesApi extends Base 
{
    public function getActionDownlines(Request $request)
    {
	try {
	    $id = $request->getQueryParam('id');
            $maxDepth = (int) $request->getQueryParam('maxDepth') ?: 6;

	    if (!$id) {
		throw new BadRequest("Missing required param id");
	    }

	    $service = $this->getServiceFactory()->create('DownlinesApi');
	    $result = $service->getDownlines($id, $maxDepth);

	    return $result;
	}  catch (BadRequest $e) {
            // Return proper error response
            http_response_code(400);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'code' => 400
            ];
        } catch (\Exception $e) {
            // Log unexpected errors
            $GLOBALS['log']->error('Downlines API Error: ' . $e->getMessage());
            http_response_code(500);
            return [
                'success' => false,
                'error' => 'Internal server error',
                'code' => 500
            ];
        }      

    }
}

