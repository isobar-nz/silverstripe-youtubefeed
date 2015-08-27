<?php

/**
 * Class ContentControllerExtension
 *
 * Updates YouTubeVideo objects if the time period between updates has elapsed
 */
class YouTubeFeedContentControllerExtension extends DataExtension
{

    /**
     * Perform the auto update
     */
    public function onAfterInit()
    {
        $service = new YouTubeFeed();
        $service->doAutoUpdate();
    }

}
