'use client'

import { ReactNode } from 'react'
import Navbar from './Navbar'
import Container from './Container'

interface MainLayoutProps {
  children: ReactNode
  containerSize?: 'sm' | 'md' | 'lg' | 'xl' | 'full'
}

export default function MainLayout({ children, containerSize = 'lg' }: MainLayoutProps) {
  return (
    <div className="min-h-screen bg-gray-50">
      <Navbar />
      <Container size={containerSize} className="py-8">
        {children}
      </Container>
    </div>
  )
}

