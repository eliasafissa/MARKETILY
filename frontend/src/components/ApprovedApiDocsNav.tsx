import { useEffect, useState, type ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import api from '../api/client';

type Props = {
  children: ReactNode;
};

export default function ApprovedApiDocsNav({ children }: Props) {
  const location = useLocation();
  const [approved, setApproved] = useState(false);

  useEffect(() => {
    let active = true;
    setApproved(false);

    const token = localStorage.getItem('token');

    if (!token) {
      return () => { active = false; };
    }

    api.get('/partner-request')
      .then(({ data }) => {
        if (!active) return;
        setApproved(data?.request?.status === 'approved');
      })
      .catch(() => {
        if (active) setApproved(false);
      });

    return () => { active = false; };
  }, [location.pathname]);

  if (!approved) return null;

  return <>{children}</>;
}