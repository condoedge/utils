<?php

namespace Condoedge\Utils\Kompo\ComplianceValidation;

use Condoedge\Utils\Models\ComplianceValidation\ComplianceIssue;
use Condoedge\Utils\Kompo\Common\Modal;

class ComplianceIssueInfoModal extends Modal
{
    const ID = 'compliance-issue-info-modal';
    public $id = self::ID;

    public $model = ComplianceIssue::class;
    protected $_Title = 'compliance.issue-details';

    protected $noHeaderButtons = true;

    public function body()
    {
        $resolved = $this->model->revalidate();

        if ($resolved) {
            return _Rows(
                _Html('compliance.issue-resolved')->class('text-positive font-semibold text-lg mb-3'),

                _Text($this->model->detail_message)->maxChars(150)->class('mb-2'),

                _Button('compliance.close')->closeModal(),
            )->class('space-y-4');
        }

        return _Rows(
            $this->model->moreDetailsElement(),

            _Text($this->model->detail_message)->maxChars(150)->class('mb-2'),

            _Button('compliance.close')->closeModal(),
        )->class('space-y-4');
    }
}
