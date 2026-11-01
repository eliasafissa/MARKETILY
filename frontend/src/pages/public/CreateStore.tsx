import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import { storeApi } from '@/api/client';
import PageTransition from '@/components/PageTransition';
import Breadcrumbs from '@/components/Breadcrumbs';

export default function CreateStore() {
  const navigate = useNavigate();
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) {
      toast.error('Name is required');
      return;
    }
    setSubmitting(true);
    try {
      const res = await storeApi.create(name.trim(), description.trim());
      toast.success(`Store created with ${res.data.product_count} products`);
      navigate('/my-stores');
    } catch (err: any) {
      toast.error(err.response?.data?.message ?? 'Failed to create store');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <PageTransition className="max-w-xl mx-auto space-y-8">
      <Breadcrumbs items={[{ label: 'Home', link: '/' }, { label: 'Create Store' }]} />
      <div>
        <h1 className="text-h1 text-gray-900 dark:text-ink-900 mb-2">Create Your Store</h1>
        <p className="text-body text-gray-600 dark:text-ink-600">
          All automation products will be added with a 10% markup. You can adjust prices afterwards.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="card-pad space-y-5">
        <div>
          <label className="label">Store name</label>
          <input
            className="input"
            value={name}
            onChange={(e) => setName(e.target.value)}
            placeholder="My Awesome Shop"
            maxLength={255}
            required
          />
        </div>
        <div>
          <label className="label">Description <span className="text-gray-500">(optional)</span></label>
          <textarea
            className="input min-h-[100px]"
            value={description}
            onChange={(e) => setDescription(e.target.value)}
            placeholder="What's your store about?"
            maxLength={1000}
          />
        </div>
        <button
          type="submit"
          disabled={submitting}
          className="btn-accent w-full disabled:opacity-50"
        >
          {submitting ? 'Creating…' : 'Create Store'}
        </button>
      </form>
    </PageTransition>
  );
}
