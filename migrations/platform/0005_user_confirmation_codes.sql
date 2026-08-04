-- =====================================================================
-- 0005 · platform · confirmation / password-reset codes as sole lookup keys
-- =====================================================================
-- Safe to re-run: MODIFY is idempotent, the sentinel-normalising UPDATEs match
-- no rows on a second pass, and the indexes use ADD UNIQUE KEY IF NOT EXISTS.
-- Requires MariaDB 10.0+ (ADD KEY IF NOT EXISTS).
-- --------------------------------------------------------

-- The registration-confirmation and password-reset email links no longer
-- carry the incremental UserID; the random code alone identifies the user.
-- For that, the code columns must (a) allow NULL for the "no active code"
-- state and (b) be UNIQUE, so a lookup by code maps to exactly one account
-- while many "inactive" accounts can coexist (MySQL/MariaDB permit multiple
-- NULLs in a UNIQUE index). The legacy '1'/'0' sentinels are normalised to
-- NULL so they neither block the UNIQUE index nor collide on lookup.

-- UserPasswordReset must allow NULL for the "no reset pending" state.
ALTER TABLE `user`
  MODIFY `UserPasswordReset` varchar(255) DEFAULT NULL;

-- Normalise legacy sentinels to NULL (must happen before adding the UNIQUE
-- index, otherwise the duplicate '0'/'1' values would violate uniqueness).
UPDATE `user` SET `UserPasswordReset` = NULL WHERE `UserPasswordReset` = '0';
UPDATE `user` SET `UserRegisterConfirmation` = NULL WHERE `UserRegisterConfirmation` = '1';

-- A code uniquely identifies one user; the 191-char prefix stays within the
-- utf8mb4 index-length limit (codes are short hex strings, so fully covered).
ALTER TABLE `user`
  ADD UNIQUE KEY IF NOT EXISTS `uniq_user_register_confirmation` (`UserRegisterConfirmation`(191)),
  ADD UNIQUE KEY IF NOT EXISTS `uniq_user_password_reset` (`UserPasswordReset`(191));
