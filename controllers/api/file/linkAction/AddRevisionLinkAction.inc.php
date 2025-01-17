<?php

/**
 * @file controllers/api/file/linkAction/AddRevisionLinkAction.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddRevisionLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to upload a revision of file currently under review.
 */

use PKP\submissionFile\SubmissionFile;

import('lib.pkp.controllers.api.file.linkAction.BaseAddFileLinkAction');

class AddRevisionLinkAction extends BaseAddFileLinkAction
{
    /**
     * Constructor
     *
     * @param $request Request
     * @param $reviewRound ReviewRound The review round to upload to.
     * @param $uploaderRoles array The ids of all roles allowed to upload
     *  in the context of this action.
     */
    public function __construct($request, $reviewRound, $uploaderRoles)
    {
        // Create the action arguments array.
        $actionArgs = [
            'fileStage' => SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            'stageId' => $reviewRound->getStageId(),
            'reviewRoundId' => $reviewRound->getId(),
            'revisionOnly' => '1'
        ];

        // Call the parent class constructor.
        parent::__construct(
            $request,
            $reviewRound->getSubmissionId(),
            $reviewRound->getStageId(),
            $uploaderRoles,
            $actionArgs,
            __('submission.review.uploadRevisionToRound', ['round' => $reviewRound->getRound()]),
            __('submission.addFile')
        );
    }
}
