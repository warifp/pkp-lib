<?php

/**
 * @file controllers/grid/files/proof/ManageProofFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManageProofFilesGridHandler
 * @ingroup controllers_grid_files_proof
 *
 * @brief Handle the editor's proof files selection grid (selects which files to include)
 */

import('lib.pkp.controllers.grid.files.SelectableSubmissionFileListCategoryGridHandler');

use PKP\core\JSONMessage;
use PKP\security\authorization\internal\RepresentationRequiredPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class ManageProofFilesGridHandler extends SelectableSubmissionFileListCategoryGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        import('lib.pkp.controllers.grid.files.SubmissionFilesCategoryGridDataProvider');
        parent::__construct(
            new SubmissionFilesCategoryGridDataProvider(SubmissionFile::SUBMISSION_FILE_PROOF),
            WORKFLOW_STAGE_ID_PRODUCTION
        );

        $this->addRoleAssignment(
            [Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER],
            [
                'fetchGrid', 'fetchCategory', 'fetchRow',
                'addFile', 'downloadFile', 'deleteFile',
                'updateProofFiles',
            ]
        );

        // Set the grid title.
        $this->setTitle('submission.pageProofs');
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

        $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        $this->addPolicy(new RepresentationRequiredPolicy($request, $args));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get the grid request parameters.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        $publication = $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
        $representation = $this->getAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION);
        return array_merge(
            parent::getRequestArgs(),
            [
                'publicationId' => $publication->getId(),
                'representationId' => $representation->getId()
            ]
        );
    }

    //
    // Public handler methods
    //
    /**
     * Save 'manage proof files' form
     *
     * @param $args array
     * @param $request PKPRequest
     *
     * @return JSONMessage JSON object
     */
    public function updateProofFiles($args, $request)
    {
        $submission = $this->getSubmission();
        $publication = $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);
        $representation = $this->getAuthorizedContextObject(ASSOC_TYPE_REPRESENTATION);

        import('lib.pkp.controllers.grid.files.proof.form.ManageProofFilesForm');
        $manageProofFilesForm = new ManageProofFilesForm($submission->getId(), $publication->getId(), $representation->getId());
        $manageProofFilesForm->readInputData();

        if ($manageProofFilesForm->validate()) {
            $manageProofFilesForm->execute(
                $this->getGridCategoryDataElements($request, $this->getStageId()),
                SubmissionFile::SUBMISSION_FILE_PROOF
            );

            // Let the calling grid reload itself
            return \PKP\db\DAO::getDataChangedEvent();
        } else {
            return new JSONMessage(false);
        }
    }
}
