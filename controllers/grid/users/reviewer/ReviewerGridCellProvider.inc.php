<?php

/**
 * @file controllers/grid/users/reviewer/ReviewerGridCellProvider.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerGridCellProvider
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Base class for a cell provider that can retrieve labels for reviewer grid rows
 */

use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridHandler;

class ReviewerGridCellProvider extends DataObjectGridCellProvider
{
    /** @var boolean Is the current user assigned as an author to this submission */
    public $_isCurrentUserAssignedAuthor;

    /**
     * Constructor
     *
     * @param $isCurrentUserAssignedAuthor boolean Is the current user assigned
     *  as an author to this submission?
     */
    public function __construct($isCurrentUserAssignedAuthor)
    {
        parent::__construct();
        $this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
    }

    //
    // Template methods from GridCellProvider
    //
    /**
     * Gathers the state of a given cell given a $row/$column combination
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return string
     */
    public function getCellState($row, $column)
    {
        $reviewAssignment = $row->getData();
        $columnId = $column->getId();
        assert($reviewAssignment instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'name':
            case 'method':
                return '';
            case 'considered':
            case 'actions':
                return $reviewAssignment->getStatus();
        }
    }

    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'name':
                $isReviewAnonymous = in_array($element->getReviewMethod(), [SUBMISSION_REVIEW_METHOD_ANONYMOUS, SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS]);
                if ($this->_isCurrentUserAssignedAuthor && $isReviewAnonymous) {
                    return ['label' => __('editor.review.anonymousReviewer')];
                }
                return ['label' => $element->getReviewerFullName()];

            case 'method':
                return ['label' => __($element->getReviewMethodKey())];

            case 'considered':
                return ['label' => $this->_getStatusText($this->getCellState($row, $column), $row)];

            case 'actions':
                // Only attach actions to this column. See self::getCellActions()
                return ['label' => ''];
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * Get cell actions associated with this row/column combination
     *
     * @param $row \PKP\controllers\grid\GridRow
     * @param $column GridColumn
     *
     * @return array an array of LinkAction instances
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $reviewAssignment = $row->getData();

        // Authors can't perform action on reviews
        if ($this->_isCurrentUserAssignedAuthor) {
            return [];
        }

        $actionArgs = [
            'submissionId' => $reviewAssignment->getSubmissionId(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
            'stageId' => $reviewAssignment->getStageId()
        ];

        $router = $request->getRouter();
        $action = false;
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        // Only attach actions to the actions column. The actions and status
        // columns share state values.
        $columnId = $column->getId();
        if ($columnId == 'actions') {
            switch ($this->getCellState($row, $column)) {
                case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                    import('lib.pkp.controllers.api.task.SendReminderLinkAction');
                    return [new SendReminderLinkAction($request, 'editor.review.reminder', $actionArgs)];
                case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
                    import('lib.pkp.controllers.api.task.SendThankYouLinkAction');
                    import('lib.pkp.controllers.review.linkAction.UnconsiderReviewLinkAction');
                    return [
                        new SendThankYouLinkAction($request, 'editor.review.thankReviewer', $actionArgs),
                        new UnconsiderReviewLinkAction($request, $reviewAssignment, $submission),
                    ];
                case REVIEW_ASSIGNMENT_STATUS_THANKED:
                    import('lib.pkp.controllers.review.linkAction.UnconsiderReviewLinkAction');
                    return [new UnconsiderReviewLinkAction($request, $reviewAssignment, $submission)];
                case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                    $user = $request->getUser();
                    import('lib.pkp.controllers.review.linkAction.ReviewNotesLinkAction');
                    return [new ReviewNotesLinkAction($request, $reviewAssignment, $submission, $user, 'grid.users.reviewer.ReviewerGridHandler', true)];
            }
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    /**
     * Provide meaningful locale keys for the various grid status states.
     *
     * @param string $state
     * @param $row \PKP\controllers\grid\GridRow
     *
     * @return string
     */
    public function _getStatusText($state, $row)
    {
        $reviewAssignment = $row->getData();
        switch ($state) {
            case REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
                return '<span class="state">' . __('editor.review.requestSent') . '</span><span class="details">' . __('editor.review.responseDue', ['date' => substr($reviewAssignment->getDateResponseDue(), 0, 10)]) . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
                return '<span class="state">' . __('editor.review.requestAccepted') . '</span><span class="details">' . __('editor.review.reviewDue', ['date' => substr($reviewAssignment->getDateDue(), 0, 10)]) . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_COMPLETE:
                return $this->_getStatusWithRecommendation('common.complete', $reviewAssignment);
            case REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                return '<span class="state overdue">' . __('common.overdue') . '</span><span class="details">' . __('editor.review.reviewDue', ['date' => substr($reviewAssignment->getDateDue(), 0, 10)]) . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                return '<span class="state overdue">' . __('common.overdue') . '</span><span class="details">' . __('editor.review.responseDue', ['date' => substr($reviewAssignment->getDateResponseDue(), 0, 10)]) . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_DECLINED:
                return '<span class="state declined" title="' . __('editor.review.requestDeclined.tooltip') . '">' . __('editor.review.requestDeclined') . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_CANCELLED:
                return '<span class="state declined" title="' . __('editor.review.requestCancelled.tooltip') . '">' . __('editor.review.requestCancelled') . '</span>';
            case REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                return  $this->_getStatusWithRecommendation('editor.review.reviewSubmitted', $reviewAssignment);
            case REVIEW_ASSIGNMENT_STATUS_THANKED:
                return  $this->_getStatusWithRecommendation('editor.review.reviewerThanked', $reviewAssignment);
            default:
                return '';
        }
    }

    /**
     * Retrieve a formatted HTML string that displays the state of the review
     * with the review recommendation if one exists. Or return just the state.
     * Only works with some states.
     *
     * @param string $statusKey Locale key for status text
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment
     *
     * @return string
     */
    public function _getStatusWithRecommendation($statusKey, $reviewAssignment)
    {
        if (!$reviewAssignment->getRecommendation()) {
            return __($statusKey);
        }

        return '<span class="state">' . __($statusKey) . '</span><span class="details">' . __('submission.recommendation', ['recommendation' => $reviewAssignment->getLocalizedRecommendation()]) . '</span>';
    }
}
