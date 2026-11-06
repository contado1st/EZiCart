<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserDocumentService;
use Illuminate\View\View;

class RegistrationReviewController extends Controller
{
    public function show(User $user, UserDocumentService $documents): View
    {
        $actor = $this->authenticatedUser();
        abort_unless($actor->canManageAccount($user), 403);

        $logistics = $actor->isLogisticsAdmin();

        return view('admin.registration-review', [
            'applicant' => $user,
            'documents' => $documents->submittedForReview($user),
            'documentRoute' => $logistics ? 'logistics-admin.users.documents' : 'admin.users.documents.show',
            'backUrl' => $logistics ? route('logistics-admin.dashboard').'#applications' : route('admin.registrations.index'),
            'approveRoute' => $logistics ? 'logistics-admin.users.approve' : 'admin.registrations.approve',
            'rejectRoute' => $logistics ? 'logistics-admin.users.reject' : 'admin.registrations.reject',
        ]);
    }
}
