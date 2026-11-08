import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import 'widgets/auth_header.dart';
import 'widgets/auth_styles.dart';

class OtpVerificationPage extends StatefulWidget {
  final String email;

  const OtpVerificationPage({super.key, required this.email});

  @override
  State<OtpVerificationPage> createState() => _OtpVerificationPageState();
}

class _OtpVerificationPageState extends State<OtpVerificationPage>
    with WidgetsBindingObserver {
  final _codeCtrl = TextEditingController();

  // Wall-clock based OTP expiry (A9: 5 minutes)
  late DateTime _otpExpiryTime;
  int _secondsUntilExpiry = 300;

  // Resend cooldown (60 seconds)
  late DateTime _resendCooldownTime;
  int _secondsUntilResend = 60;

  Timer? _ticker;
  bool _isResending = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _resetTimers();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _ticker?.cancel();
    _codeCtrl.dispose();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      // Recalculate remaining seconds based on wall-clock when returning from mail app (TC-BUY-038)
      _updateRemainingSeconds();
    }
  }

  void _resetTimers() {
    final now = DateTime.now();
    _otpExpiryTime = now.add(const Duration(minutes: 5));
    _resendCooldownTime = now.add(const Duration(seconds: 60));
    _updateRemainingSeconds();

    _ticker?.cancel();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      if (mounted) {
        _updateRemainingSeconds();
      }
    });
  }

  void _updateRemainingSeconds() {
    final now = DateTime.now();
    final expiryDiff = _otpExpiryTime.difference(now).inSeconds;
    final resendDiff = _resendCooldownTime.difference(now).inSeconds;

    setState(() {
      _secondsUntilExpiry = expiryDiff > 0 ? expiryDiff : 0;
      _secondsUntilResend = resendDiff > 0 ? resendDiff : 0;
    });
  }

  void _verify() async {
    final code = _codeCtrl.text.trim();

    // Format validation (TC-BUY-025)
    if (code.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please enter the 6-digit verification code.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (code.length != 6 || !RegExp(r'^\d{6}$').hasMatch(code)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Verification code must be exactly 6 digits.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    // Expiry check (TC-BUY-026)
    if (_secondsUntilExpiry <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('This verification code has expired. Please tap Resend Code.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.verifyOtp(widget.email, code);

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Email verified successfully! Welcome to EZiCart.'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  }

  void _resend() async {
    if (_secondsUntilResend > 0 || _isResending) return;
    setState(() => _isResending = true);

    final auth = context.read<AuthProvider>();
    final success = await auth.resendOtp(widget.email);

    if (!mounted) return;
    setState(() => _isResending = false);

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('A fresh 6-digit verification code has been sent to your email.'),
          backgroundColor: Colors.green,
        ),
      );
      _resetTimers();
    }
  }

  String _formatTime(int totalSeconds) {
    final minutes = totalSeconds ~/ 60;
    final seconds = totalSeconds % 60;
    return '${minutes.toString().padLeft(2, '0')}:${seconds.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFFF6F8FC),
      body: GestureDetector(
        onTap: () => FocusScope.of(context).unfocus(),
        behavior: HitTestBehavior.opaque,
        child: SingleChildScrollView(
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          child: Column(
            children: [
              const AuthHeader(
                title: 'Email Verification',
                subtitle: 'Verify your email to activate your buyer account',
                showBack: true,
                headerHeight: 220,
              ),

              Transform.translate(
                offset: const Offset(0, -32),
                child: Container(
                  margin: const EdgeInsets.symmetric(horizontal: 16),
                  padding: const EdgeInsets.fromLTRB(22, 26, 22, 24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(24),
                    boxShadow: [
                      BoxShadow(
                        color: const Color(0xFF140209).withValues(alpha: 0.08),
                        blurRadius: 24,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Center(
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: AppTheme.primarySoft,
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppTheme.primary.withValues(alpha: 0.25),
                              width: 1.5,
                            ),
                          ),
                          child: const Icon(
                            Icons.mark_email_read_rounded,
                            size: 36,
                            color: AppTheme.primary,
                          ),
                        ),
                      ),
                      const SizedBox(height: 14),

                      const Text(
                        'Enter 6-Digit Code',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.ink,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'We have sent a verification code to\n${widget.email}',
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontSize: 13, color: AppTheme.muted, height: 1.4),
                      ),
                      const SizedBox(height: 12),

                      // Code Expiry Countdown Badge (TC-BUY-026, TC-BUY-038)
                      Center(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                          decoration: BoxDecoration(
                            color: _secondsUntilExpiry > 0 ? const Color(0xFFF1F5F9) : const Color(0xFFFEF2F2),
                            borderRadius: BorderRadius.circular(8),
                            border: Border.all(
                              color: _secondsUntilExpiry > 0 ? const Color(0xFFE2E8F0) : const Color(0xFFFECACA),
                            ),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.timer_outlined,
                                size: 14,
                                color: _secondsUntilExpiry > 0 ? const Color(0xFF64748B) : Colors.red.shade700,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                _secondsUntilExpiry > 0
                                    ? 'Code expires in ${_formatTime(_secondsUntilExpiry)}'
                                    : 'Code has expired',
                                style: TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: _secondsUntilExpiry > 0 ? const Color(0xFF475569) : Colors.red.shade800,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 20),

                      if (auth.errorMessage != null)
                        AuthStyles.errorBanner(auth.errorMessage!),

                      // OTP Field with paste support (TC-BUY-025, TC-BUY-037)
                      TextField(
                        controller: _codeCtrl,
                        keyboardType: TextInputType.number,
                        enableInteractiveSelection: true,
                        maxLength: 6,
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 26,
                          letterSpacing: 10,
                          fontWeight: FontWeight.bold,
                          color: AppTheme.primary,
                        ),
                        decoration: AuthStyles.inputDecoration(
                          label: 'Verification Code',
                          hint: '000000',
                          prefixIcon: Icons.pin_rounded,
                        ).copyWith(counterText: ''),
                        onChanged: (val) {
                          if (val.trim().length == 6) {
                            _verify();
                          }
                        },
                      ),
                      const SizedBox(height: 24),

                      AuthStyles.gradientButton(
                        onPressed: _verify,
                        text: 'Verify & Continue',
                        isLoading: auth.isLoading,
                        trailingIcon: Icons.arrow_forward_rounded,
                      ),
                      const SizedBox(height: 18),

                      Center(
                        child: TextButton(
                          onPressed: (_secondsUntilResend == 0 && !_isResending) ? _resend : null,
                          child: _isResending
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(strokeWidth: 2, color: AppTheme.primary),
                                )
                              : Text(
                                  _secondsUntilResend > 0
                                      ? 'Resend code in ${_secondsUntilResend}s'
                                      : 'Resend Code',
                                  style: TextStyle(
                                    color: _secondsUntilResend == 0 ? AppTheme.primary : AppTheme.muted,
                                    fontWeight: FontWeight.w600,
                                    fontSize: 13,
                                  ),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
