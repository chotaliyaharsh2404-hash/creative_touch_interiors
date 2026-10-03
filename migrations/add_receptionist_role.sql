-- Migration: Extend admin_users role ENUM to include 'receptionist'
-- Project: Creative Touch Interiors
-- Safe & idempotent migration

ALTER TABLE `admin_users` 
  MODIFY COLUMN `role` ENUM('admin', 'super_admin', 'receptionist') NOT NULL DEFAULT 'admin';
