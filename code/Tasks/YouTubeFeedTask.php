<?php

/**
 * Class YouTubeFeedTask
 *
 * Provides a method of forcing a YouTubeFeed update via CLI (intended for cronjobs)
 *      framework/sake YouTubeFeedTask flush=all
 */
class YouTubeFeedTask extends CliController
{

    /**
     * Force the auto update when called through CLI
     */
    public function process()
    {
        $service = new YouTubeFeed();
        $service->doAutoUpdate(true);
    }
}
