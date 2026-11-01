import { useEffect, useState } from 'react';
import { adminPartnerApi } from '@/api/client';
import toast from 'react-hot-toast';
import Button from '@/components/Button';
import PageTransition from '@/components/PageTransition';
import { useI18n } from '@/i18n';
import { CopyToClipboard } from 'react-copy-to-clipboard';

type RequestStatus = 'pending' | 'approved' | 'rejected';

interface PartnerRequest {
  id: number;
  user_id: number;
  store_name: string;
  store_url: string;
  phone: string;
  notes: string | null;
  status: RequestStatus;
  rejected_reason: string | null;
  approved_at: string | null;
  approved_by: number | null;
  created_at: string;
  updated_at: string;
  user: { id: number; name: string; email: string };
  approver: { id: number; name: string; email: string } | null;
}

export default function PartnerRequests() {
  const { t } = useI18n();
  const [requests, setRequests] = useState<PartnerRequest[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState<RequestStatus | 'all'>('all');
  const [showKeyModal, setShowKeyModal] = useState<string | null>(null);
  const [rejectReason, setRejectReason] = useState('');
  const [rejectingId, setRejectingId] = useState<number | null>(null);
  const [approvingId, setApprovingId] = useState<number | null>(null);

  const fetchRequests = async () => {
    try {
      const params: Record<string, string> = {};
      if (statusFilter !== 'all') params.status = statusFilter;
      const res = await adminPartnerApi.list(params);
      setRequests(res.data.data.data ?? []);
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchRequests();
  }, [statusFilter]);

  const handleApprove = async (id: number) => {
    if (!confirm(t('partnerRequests.confirmApprove'))) return;
    setApprovingId(id);
    try {
      const res = await adminPartnerApi.approve(id);
      toast.success(t('partnerRequests.apiKeyGenerated'));
      if (res.data.api_key) {
        setShowKeyModal(res.data.api_key);
      }
      fetchRequests();
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    } finally {
      setApprovingId(null);
    }
  };

  const handleReject = async (id: number) => {
    if (!rejectReason.trim()) {
      toast.error(t('partnerRequests.rejectReasonPlaceholder'));
      return;
    }
    if (!confirm(t('partnerRequests.confirmReject'))) return;
    setRejectingId(id);
    try {
      await adminPartnerApi.reject(id, rejectReason);
      toast.success(t('common.success'));
      fetchRequests();
      setRejectReason('');
      setRejectingId(null);
    } catch (err: any) {
      toast.error(err?.response?.data?.error ?? t('common.failed'));
    }
  };

  const getStatusBadge = (status: RequestStatus) => {
    const classes = {
      pending: 'badge-pending',
      approved: 'badge-completed',
      rejected: 'badge-rejected',
    };
    const labels = {
      pending: t('partnerRequests.filters.pending'),
      approved: t('partnerRequests.filters.approved'),
      rejected: t('partnerRequests.filters.rejected'),
    };
    return <span className={`badge ${classes[status]}`}>{labels[status]}</span>;
  };

  return (
    <PageTransition className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-h1 text-gray-900 dark:text-ink-900">{t('partnerRequests.title')}</h1>
      </div>

      <div className="flex flex-wrap gap-2 mb-4">
        {(['all', 'pending', 'approved', 'rejected'] as const).map((s) => (
          <button
            key={s}
            onClick={() => setStatusFilter(s)}
            className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
              statusFilter === s
                ? 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300'
                : 'text-gray-700 dark:text-ink-300 hover:bg-gray-100 dark:hover:bg-ink-200'
            }`}
          >
            {t(`partnerRequests.filters.${s}`)}
          </button>
        ))}
      </div>

      {loading ? (
        <div className="flex items-center justify-center py-12">
          <span className="w-8 h-8 rounded-full border-2 border-green-500 border-t-transparent animate-spin" />
        </div>
      ) : requests.length === 0 ? (
        <div className="card-pad text-center py-12">
          <p className="text-body text-gray-600 dark:text-ink-600">{t('partnerRequests.noRequests')}</p>
        </div>
      ) : (
        <div className="card-pad overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-gray-500 dark:text-ink-500 border-b border-gray-200 dark:border-ink-200">
                <th className="pb-3">{t('partnerRequests.table.user')}</th>
                <th className="pb-3">{t('partnerRequests.table.storeName')}</th>
                <th className="pb-3">{t('partnerRequests.table.storeUrl')}</th>
                <th className="pb-3">{t('partnerRequests.table.phone')}</th>
                <th className="pb-3">{t('partnerRequests.table.status')}</th>
                <th className="pb-3">{t('partnerRequests.table.date')}</th>
                <th className="pb-3 text-right">{t('partnerRequests.table.actions')}</th>
              </tr>
            </thead>
            <tbody>
              {requests.map((req) => (
                <tr key={req.id} className="border-b border-gray-100 dark:border-ink-200">
                  <td className="py-3">
                    <div>
                      <p className="font-medium text-gray-900 dark:text-ink-900">{req.user.name}</p>
                      <p className="text-gray-500 dark:text-ink-500">{req.user.email}</p>
                    </div>
                  </td>
                  <td className="py-3 font-medium text-gray-900 dark:text-ink-900">{req.store_name}</td>
                  <td className="py-3">
                    <a href={req.store_url} target="_blank" rel="noopener noreferrer" className="text-green-600 dark:text-green-400 hover:underline truncate block max-w-xs">
                      {req.store_url}
                    </a>
                  </td>
                  <td className="py-3 text-gray-700 dark:text-ink-300">{req.phone}</td>
                  <td className="py-3">{getStatusBadge(req.status)}</td>
                  <td className="py-3 text-gray-500 dark:text-ink-500">
                    {new Date(req.created_at).toLocaleDateString()}
                  </td>
                  <td className="py-3 text-right space-x-2">
                    {req.status === 'pending' && (
                      <>
                        <Button
                          variant="accent"
                          size="sm"
                          onClick={() => handleApprove(req.id)}
                          loading={approvingId === req.id}
                        >
                          {t('partnerRequests.approve')}
                        </Button>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => { setRejectingId(req.id); setRejectReason(''); }}
                          loading={rejectingId === req.id}
                        >
                          {t('partnerRequests.reject')}
                        </Button>
                      </>
                    )}
                    {req.status === 'rejected' && req.rejected_reason && (
                      <button
                        onClick={() => { setRejectReason(req.rejected_reason ?? ''); setRejectingId(req.id); }}
                        className="text-gray-500 dark:text-ink-500 hover:text-red-600 dark:hover:text-red-400 text-xs"
                      >
                        {t('partnerRequests.rejectReason')}: {req.rejected_reason.slice(0, 30)}...
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {showKeyModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-ink-800 rounded-2xl p-6 max-w-md w-full space-y-4">
            <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('partnerRequests.apiKeyGenerated')}</h2>
            <p className="text-body text-gray-600 dark:text-ink-600">{t('partnerRequests.keyWarning')}</p>
            <div className="flex items-center gap-3">
              <div className="flex-1 font-mono text-small bg-gray-100 dark:bg-ink-200 px-4 py-3 rounded-lg break-all">
                {showKeyModal}
              </div>
              <CopyToClipboard text={showKeyModal} onCopy={() => toast.success(t('partnerRequests.keyCopied'))}>
                <Button variant="secondary" size="sm">{t('partnerRequests.copyKey')}</Button>
              </CopyToClipboard>
            </div>
            <Button variant="accent" className="w-full" onClick={() => setShowKeyModal(null)}>
              {t('common.close')}
            </Button>
          </div>
        </div>
      )}

      {rejectingId && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white dark:bg-ink-800 rounded-2xl p-6 max-w-md w-full space-y-4">
            <h2 className="text-h3 text-gray-900 dark:text-ink-900">{t('partnerRequests.reject')}</h2>
            <textarea
              className="input min-h-[100px]"
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              placeholder={t('partnerRequests.rejectReasonPlaceholder')}
              required
            />
            <div className="flex gap-3">
              <Button variant="secondary" className="flex-1" onClick={() => { setRejectingId(null); setRejectReason(''); }}>
                {t('common.cancel')}
              </Button>
              <Button variant="danger" className="flex-1" onClick={() => handleReject(rejectingId)} loading={rejectingId !== null}>
                {t('partnerRequests.reject')}
              </Button>
            </div>
          </div>
        </div>
      )}
    </PageTransition>
  );
}