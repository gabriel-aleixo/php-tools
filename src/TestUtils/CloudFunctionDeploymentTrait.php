<?php
/**
 * Copyright 2020 Google LLC.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Google\Cloud\TestUtils;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Cloud\TestUtils\GcloudWrapper\CloudFunction;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * Trait CloudFunctionDeploymentTrait.
 */
trait CloudFunctionDeploymentTrait
{
    use TestTrait;
    use DeploymentTrait;

    /** @var \Google\Cloud\TestUtils\GcloudWrapper\CloudFunction */
    private static $fn;

    /**
     * Prepare the Cloud Function.
     *
     * @beforeClass
     */
    public static function setUpFunction()
    {
        // If $fn is reinitialized, deployment state is reset.
        if (empty(self::$fn)) {
            $props = [
                'projectId' => self::requireEnv('GOOGLE_PROJECT_ID')
            ];
            if (isset(self::$entryPoint)) {
                $props['entryPoint'] = self::$entryPoint;
            }
            if (isset(self::$functionSignatureType)) {
                $props['functionSignatureType'] = self::$functionSignatureType;
            }
            self::$fn = CloudFunction::fromArray(
                self::initFunctionProperties($props)
            );
        }
    }

    /**
     * Customize setUpFunction properties.
     *
     * Example:
     *
     *     $props['dir'] = 'path/to/function-dir';
     *     $props['region'] = 'us-west1';
     *     return $props;
     */
    private static function initFunctionProperties(array $props = [])
    {
        return $props;
    }

    /**
     * Prepare to deploy app, called from DeploymentTrait::deployApp().
     */
    private static function beforeDeploy()
    {
        // Ensure function is set up before depoyment is attempted.
        if (empty(self::$fn)) {
            self::setUpFunction();
        }
    }

    /**
     * Deploy the Cloud Function, called from DeploymentTrait::deployApp().
     *
     * Override this in your TestCase to change deploy behaviors.
     */
    private static function doDeploy()
    {
        return self::$fn->deploy();
    }

    /**
     * Delete a deployed Cloud Function.
     */
    private static function doDelete()
    {
        self::$fn->delete();
    }

    /**
     * Set up the client.
     *
     * @before
     */
    public function setUpClient()
    {
        // Get the Cloud Function URL.
        $targetAudience = self::getBaseUri();
        if ($targetAudience === '') {
            // A URL was not available for this function.
            // Skip client setup.
            return;
        }

        // Create middleware.
        $middleware = ApplicationDefaultCredentials::getIdTokenMiddleware($targetAudience);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        // Create the HTTP client.
        $this->client = new Client([
            'handler' => $stack,
            'auth' => 'google_auth',
            'base_uri' => $targetAudience,
            'http_errors' => false,
        ]);
    }

    public function getBaseUri()
    {
        return self::$fn->getBaseUrl(getenv("GOOGLE_SKIP_DEPLOYMENT") === 'true');
    }
}
