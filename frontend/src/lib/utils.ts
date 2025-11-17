import { type ClassValue, clsx } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export function getRoleColor(role: string): string {
  const roleColors: Record<string, string> = {
    backend: '#8b5cf6',
    frontend: '#06b6d4',
    qa: '#f59e0b',
    pm: '#ec4899',
    designer: '#f97316',
    employee: '#64748b',
    owner: '#dc2626',
  }
  return roleColors[role.toLowerCase()] || roleColors.employee
}

export function getRoleDisplayName(role: string): string {
  const roleNames: Record<string, string> = {
    backend: 'BACKEND',
    frontend: 'FRONTEND',
    qa: 'QA',
    pm: 'PROJECT MANAGER',
    designer: 'DESIGNER',
    employee: 'EMPLOYEE',
    owner: 'OWNER',
  }
  return roleNames[role.toLowerCase()] || role.toUpperCase()
}

export function formatDate(date: string | Date): string {
  const d = typeof date === 'string' ? new Date(date) : date
  return new Intl.DateTimeFormat('bg-BG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(d)
}

