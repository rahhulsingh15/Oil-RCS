<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Block;
use App\Notifications\DeadlineAlert;
use App\Services\AlertService;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/** In-portal deadline notifications of the logged-in user (no email). */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->where('type', DeadlineAlert::class)
            ->when($request->input('show') === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->when($request->filled('type'), fn ($q) => $q->where('data->type', $request->input('type')))
            ->when($request->filled('block'), fn ($q) => $q->where('data->block_id', $request->integer('block')))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->where('type', DeadlineAlert::class)->count(),
            'blocks' => Block::visibleTo($user)->orderBy('code')->get(['id', 'code']),
        ]);
    }

    /** Open a notification: marks it read and goes to the deadline in the Compliance Monitor. */
    public function open(Request $request, string $id)
    {
        $notification = $this->find($request, $id);
        $notification->markAsRead();

        return redirect()->route('compliance.index', ['block' => $notification->data['block_id'] ?? null]);
    }

    public function toggle(Request $request, string $id)
    {
        $notification = $this->find($request, $id);
        $notification->read() ? $notification->markAsUnread() : $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->where('type', DeadlineAlert::class)->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /** "Run check now": the same check the scheduler runs every morning. */
    public function run(Request $request, AlertService $alerts)
    {
        $result = $alerts->run();

        AuditLog::record('alerts-check', null, [], $result,
            "Deadline check run by hand: {$result['upcoming']} upcoming, {$result['overdue']} overdue, {$result['notified']} notifications");

        $new = $result['upcoming'] + $result['overdue'];

        return back()->with('success', $new
            ? "Check done: {$result['upcoming']} new upcoming and {$result['overdue']} new overdue deadline(s) notified to {$result['notified']} user notification(s)."
            : 'Check done: no new deadlines to notify. Everything already notified is unchanged.');
    }

    private function find(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->where('type', DeadlineAlert::class)->findOrFail($id);
    }
}
