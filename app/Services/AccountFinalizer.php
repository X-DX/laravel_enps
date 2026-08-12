<?php

namespace App\Services;

use App\Models\Subscriber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Finalizes draft subscribers: safely allots each one an account number, then marks it
 * Finalized ('F'). The account number comes from a per-department counter, so the increment
 * must be atomic and locked — see finalize().
 */

class AccountFinalizer
{
    /**
     * Finalize ONE draft. Returns the new account number.
     * Must run inside a DB transaction (finalizeMany() provides one).
     */
    public function finalize(Subscriber $subscriber): string
    {
        $deptCode = trim((string) $subscriber->nameofdept);

        // Atomically add 1 to the department's counter. This single UPDATE also LOCKS that
        // counter row until the surrounding transaction commits — so if two operators
        // finalize in the same department at once, the second one waits and gets the next
        // number. (Same idea as the legacy; the UPDATE + read below work on Postgres AND the
        // SQLite used in tests, unlike Postgres-only "RETURNING".)

        $affected = DB::update(
            'UPDATE account_sequence SET account_seq_no = account_seq_no + 1 WHERE trim(dept_code) = ?',
            [$deptCode]
        );

        if ($affected === 0) {
            throw new RuntimeException("No account counter exists for department {$deptCode}.");
        }

        // Read back the value THIS transaction just set (it sees its own change; the row is
        // locked, so no other finalize can have changed it in between).
        $seq = (int) DB::table('account_sequence')
            ->whereRaw('trim(dept_code) = ?', [$deptCode])
            ->value('account_seq_no');

        $prefix = $subscriber->pension_type === 'U' ? 'UPS' : 'NPS';
        $accountNo = 'AP/' . $prefix . '/' . $deptCode . '/' . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        $subscriber->update([
            'account_no' => $accountNo,
            'save_flag' => 'F',
            'finalize_date' => now()->toDateString(),
        ]);
        return $accountNo;
    }

    /**
     * Finalize many drafts in ONE transaction — all succeed together, or all roll back.
     *
     * @param  array<int>  $ids
     * @return array<int, array{name: string, account_no: string}>
     */
    public function finalizeMany(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $done = [];

            foreach ($ids as $id) {
                // Only finalize rows that are still drafts (skips already-finalized / missing).
                $subscriber = Subscriber::where('save_flag', 'T')->find($id);

                if ($subscriber === null) {
                    continue;
                }

                $done[] = [
                    'name' => $subscriber->name,
                    'account_no' => $this->finalize($subscriber),
                ];
            }
            return $done;
        });
    }
}
