import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../common/psgc_address_form.dart';
import 'otp_verification_page.dart';
import 'widgets/auth_header.dart';
import 'widgets/auth_styles.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  int _currentStep = 0; // 0: Details, 1: Address, 2: Security

  final _step1FormKey = GlobalKey<FormState>();
  final _step3FormKey = GlobalKey<FormState>();

  // Step 1 Controllers
  final _firstNameCtrl = TextEditingController();
  final _middleNameCtrl = TextEditingController();
  final _lastNameCtrl = TextEditingController();
  final _suffixCtrl = TextEditingController();
  final _emailCtrl = TextEditingController();
  final _contactCtrl = TextEditingController();
  final _birthdayCtrl = TextEditingController();
  DateTime? _selectedBirthday;

  // Step 2 Address Data
  AddressFormData _addressData = AddressFormData();

  // Step 3 Controllers
  final _passwordCtrl = TextEditingController();
  final _confirmPasswordCtrl = TextEditingController();
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _termsAccepted = false;

  @override
  void initState() {
    super.initState();
    _passwordCtrl.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _firstNameCtrl.dispose();
    _middleNameCtrl.dispose();
    _lastNameCtrl.dispose();
    _suffixCtrl.dispose();
    _emailCtrl.dispose();
    _contactCtrl.dispose();
    _birthdayCtrl.dispose();
    _passwordCtrl.dispose();
    _confirmPasswordCtrl.dispose();
    super.dispose();
  }

  Future<void> _selectBirthday() async {
    final now = DateTime.now();
    final maxDate = DateTime(now.year - 18, now.month, now.day);
    final initial = _selectedBirthday ?? DateTime(now.year - 20, now.month, now.day);

    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1920),
      lastDate: maxDate,
      helpText: 'Select Date of Birth (Must be 18+)',
    );

    if (picked != null) {
      setState(() {
        _selectedBirthday = picked;
        _birthdayCtrl.text = DateFormat('yyyy-MM-dd').format(picked);
      });
    }
  }

  void _nextStep() {
    if (_currentStep == 0) {
      if (!_step1FormKey.currentState!.validate()) return;
      setState(() => _currentStep = 1);
    } else if (_currentStep == 1) {
      setState(() => _currentStep = 2);
    }
  }

  void _prevStep() {
    if (_currentStep > 0) {
      setState(() => _currentStep--);
    } else {
      Navigator.of(context).maybePop();
    }
  }

  bool get _hasMinLength => _passwordCtrl.text.length >= 8;
  bool get _hasUpper => _passwordCtrl.text.contains(RegExp(r'[A-Z]'));
  bool get _hasLower => _passwordCtrl.text.contains(RegExp(r'[a-z]'));
  bool get _hasNumber => _passwordCtrl.text.contains(RegExp(r'[0-9]'));
  bool get _hasSymbol => _passwordCtrl.text.contains(RegExp(r'[!@#\$%^&*(),.?":{}|<>]'));
  bool get _isPasswordValid =>
      _hasMinLength && _hasUpper && _hasLower && _hasNumber && _hasSymbol;

  void _submitRegistration() async {
    if (!_step3FormKey.currentState!.validate()) return;

    if (!_termsAccepted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Please accept the Terms of Service and Privacy Policy to proceed.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    final auth = context.read<AuthProvider>();
    auth.clearError();

    final success = await auth.register(
      firstName: _firstNameCtrl.text.trim(),
      middleName: _middleNameCtrl.text.trim().isNotEmpty ? _middleNameCtrl.text.trim() : null,
      lastName: _lastNameCtrl.text.trim(),
      suffix: _suffixCtrl.text.trim().isNotEmpty ? _suffixCtrl.text.trim() : null,
      email: _emailCtrl.text.trim(),
      contactNo: _contactCtrl.text.trim(),
      birthday: _birthdayCtrl.text.trim(),
      password: _passwordCtrl.text,
      region: _addressData.region.isNotEmpty ? _addressData.region : null,
      province: _addressData.province.isNotEmpty ? _addressData.province : null,
      municipality: _addressData.municipality.isNotEmpty ? _addressData.municipality : null,
      barangay: _addressData.barangay.isNotEmpty ? _addressData.barangay : null,
      streetAddress: _addressData.streetAddress,
      houseNumber: _addressData.houseNumber,
      postalCode: _addressData.postalCode,
    );

    if (!mounted) return;

    if (success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Account created! A 6-digit verification code has been sent to your email.'),
          backgroundColor: Colors.green,
        ),
      );
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(
          builder: (_) => OtpVerificationPage(
            email: _emailCtrl.text.trim(),
          ),
        ),
      );
    }
  }

  void _showPolicyDialog(String title, String content) {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: SingleChildScrollView(
          child: Text(content, style: const TextStyle(fontSize: 13, height: 1.45, color: Color(0xFF4A5568))),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('I Understand', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold)),
          ),
        ],
      ),
    );
  }

  Widget _buildStepIndicator() {
    final steps = [
      {'title': 'Details', 'icon': Icons.person_outline_rounded},
      {'title': 'Address', 'icon': Icons.local_shipping_outlined},
      {'title': 'Security', 'icon': Icons.security_rounded},
    ];

    return Container(
      margin: const EdgeInsets.only(bottom: 22),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE2E8F0)),
      ),
      child: Row(
        children: List.generate(steps.length * 2 - 1, (index) {
          if (index.isOdd) {
            final stepBefore = index ~/ 2;
            final isCompleted = _currentStep > stepBefore;
            return Expanded(
              child: Container(
                height: 2,
                color: isCompleted ? AppTheme.primary : const Color(0xFFCBD5E1),
              ),
            );
          }

          final stepIndex = index ~/ 2;
          final isCompleted = _currentStep > stepIndex;
          final isCurrent = _currentStep == stepIndex;

          return GestureDetector(
            onTap: () {
              if (stepIndex < _currentStep) {
                setState(() => _currentStep = stepIndex);
              }
            },
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  width: 32,
                  height: 32,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: isCompleted
                        ? AppTheme.primary
                        : isCurrent
                            ? AppTheme.primary
                            : Colors.white,
                    border: Border.all(
                      color: (isCompleted || isCurrent) ? AppTheme.primary : const Color(0xFFCBD5E1),
                      width: 2,
                    ),
                    boxShadow: isCurrent
                        ? [
                            BoxShadow(
                              color: AppTheme.primary.withValues(alpha: 0.3),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ]
                        : null,
                  ),
                  child: Center(
                    child: isCompleted
                        ? const Icon(Icons.check, size: 16, color: Colors.white)
                        : Text(
                            '${stepIndex + 1}',
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.bold,
                              color: isCurrent ? Colors.white : const Color(0xFF64748B),
                            ),
                          ),
                  ),
                ),
                const SizedBox(width: 6),
                Text(
                  steps[stepIndex]['title'] as String,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: isCurrent ? FontWeight.bold : FontWeight.w500,
                    color: isCurrent ? AppTheme.primary : const Color(0xFF64748B),
                  ),
                ),
              ],
            ),
          );
        }),
      ),
    );
  }

  Widget _buildStep1() {
    return Form(
      key: _step1FormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.badge_outlined, color: AppTheme.primary, size: 20),
              SizedBox(width: 8),
              Text(
                'Personal Information',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Enter your authentic details as they appear on your government ID.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 18),

          // First Name & Middle Name
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 3,
                child: TextFormField(
                  controller: _firstNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  decoration: AuthStyles.inputDecoration(
                    label: 'First Name *',
                    hint: 'Juan',
                  ),
                  validator: (v) {
                    if (v == null || v.trim().length < 2) return 'Min 2 characters';
                    if (!RegExp(r"^[\p{L}\p{M} .\x27-]+$", unicode: true).hasMatch(v.trim())) {
                      return 'Letters only';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _middleNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  decoration: AuthStyles.inputDecoration(
                    label: 'Middle Name',
                    hint: 'Santos',
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Last Name & Suffix
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                flex: 3,
                child: TextFormField(
                  controller: _lastNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  decoration: AuthStyles.inputDecoration(
                    label: 'Last Name *',
                    hint: 'Dela Cruz',
                  ),
                  validator: (v) {
                    if (v == null || v.trim().length < 2) return 'Min 2 characters';
                    if (!RegExp(r"^[\p{L}\p{M} .\x27-]+$", unicode: true).hasMatch(v.trim())) {
                      return 'Letters only';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _suffixCtrl,
                  textCapitalization: TextCapitalization.words,
                  textInputAction: TextInputAction.next,
                  decoration: AuthStyles.inputDecoration(
                    label: 'Suffix',
                    hint: 'Jr., III',
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // Email
          TextFormField(
            controller: _emailCtrl,
            keyboardType: TextInputType.emailAddress,
            textInputAction: TextInputAction.next,
            autocorrect: false,
            decoration: AuthStyles.inputDecoration(
              label: 'Email Address *',
              hint: 'juan@example.com',
              prefixIcon: Icons.email_outlined,
            ),
            validator: (v) {
              if (v == null || v.trim().isEmpty) return 'Email is required';
              if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(v.trim())) {
                return 'Enter a valid email address';
              }
              return null;
            },
          ),
          const SizedBox(height: 14),

          // Contact Number
          TextFormField(
            controller: _contactCtrl,
            keyboardType: TextInputType.phone,
            textInputAction: TextInputAction.next,
            decoration: AuthStyles.inputDecoration(
              label: 'Contact Number (PH Mobile) *',
              hint: '09XXXXXXXXX',
              prefixIcon: Icons.phone_android_rounded,
              helperText: '11 digits starting with 09',
            ),
            validator: (v) {
              if (v == null || v.trim().isEmpty) return 'Contact number is required';
              final cleaned = v.trim().replaceAll(' ', '');
              if (!RegExp(r'^(09\d{9}|\+639\d{9})$').hasMatch(cleaned)) {
                return 'Enter a valid 11-digit PH mobile number';
              }
              return null;
            },
          ),
          const SizedBox(height: 14),

          // Birthday
          TextFormField(
            controller: _birthdayCtrl,
            readOnly: true,
            onTap: _selectBirthday,
            decoration: AuthStyles.inputDecoration(
              label: 'Date of Birth (18+ only) *',
              hint: 'YYYY-MM-DD',
              prefixIcon: Icons.cake_outlined,
              suffixIcon: const Icon(Icons.calendar_month_outlined, color: Color(0xFF718096), size: 20),
            ),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Please select your birthday' : null,
          ),
          const SizedBox(height: 24),

          AuthStyles.gradientButton(
            onPressed: _nextStep,
            text: 'Continue to Delivery Address',
            trailingIcon: Icons.arrow_forward_rounded,
          ),
        ],
      ),
    );
  }

  Widget _buildStep2() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(Icons.local_shipping_outlined, color: AppTheme.primary, size: 20),
                      SizedBox(width: 8),
                      Text(
                        'Delivery Address',
                        style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
                      ),
                    ],
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Optional - can also be added anytime during checkout',
                    style: TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                ],
              ),
            ),
            TextButton(
              onPressed: () {
                _addressData = AddressFormData();
                _nextStep();
              },
              child: const Text('Skip for now', style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 13)),
            ),
          ],
        ),
        const SizedBox(height: 16),

        PsgcAddressForm(
          initialData: _addressData,
          isOptional: true,
          onChanged: (data) => _addressData = data,
        ),
        const SizedBox(height: 24),

        Row(
          children: [
            Expanded(
              flex: 1,
              child: AuthStyles.secondaryButton(
                onPressed: _prevStep,
                text: 'Back',
                leadingIcon: Icons.arrow_back_rounded,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: AuthStyles.gradientButton(
                onPressed: _nextStep,
                text: 'Next: Security',
                trailingIcon: Icons.arrow_forward_rounded,
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildRequirementChip(String label, bool isMet) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: isMet ? const Color(0xFFECFDF5) : const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: isMet ? const Color(0xFF6EE7B7) : const Color(0xFFE2E8F0),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            isMet ? Icons.check_circle_rounded : Icons.radio_button_unchecked_rounded,
            size: 13,
            color: isMet ? const Color(0xFF059669) : const Color(0xFF94A3B8),
          ),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: isMet ? FontWeight.w600 : FontWeight.normal,
              color: isMet ? const Color(0xFF065F46) : const Color(0xFF64748B),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStep3() {
    final auth = context.watch<AuthProvider>();

    return Form(
      key: _step3FormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.shield_outlined, color: AppTheme.primary, size: 20),
              SizedBox(width: 8),
              Text(
                'Security & Agreement',
                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
              ),
            ],
          ),
          const SizedBox(height: 4),
          const Text(
            'Create a strong password to protect your orders and personal data.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 18),

          // Password
          TextFormField(
            controller: _passwordCtrl,
            obscureText: _obscurePassword,
            textInputAction: TextInputAction.next,
            decoration: AuthStyles.inputDecoration(
              label: 'Password *',
              hint: 'Min 8 characters',
              prefixIcon: Icons.lock_outline_rounded,
              suffixIcon: IconButton(
                icon: Icon(
                  _obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  color: const Color(0xFF718096),
                  size: 20,
                ),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            validator: (v) {
              if (v == null || v.isEmpty) return 'Password is required';
              if (v.length < 8) return 'Must be at least 8 characters';
              if (!_isPasswordValid) {
                return 'Password does not satisfy all criteria below';
              }
              return null;
            },
          ),
          const SizedBox(height: 10),

          // Password criteria chips
          Wrap(
            spacing: 6,
            runSpacing: 6,
            children: [
              _buildRequirementChip('8+ characters', _hasMinLength),
              _buildRequirementChip('Uppercase', _hasUpper),
              _buildRequirementChip('Lowercase', _hasLower),
              _buildRequirementChip('Number (0-9)', _hasNumber),
              _buildRequirementChip('Symbol (!@#)', _hasSymbol),
            ],
          ),
          const SizedBox(height: 14),

          // Confirm Password
          TextFormField(
            controller: _confirmPasswordCtrl,
            obscureText: _obscureConfirmPassword,
            textInputAction: TextInputAction.done,
            onFieldSubmitted: (_) => _submitRegistration(),
            decoration: AuthStyles.inputDecoration(
              label: 'Confirm Password *',
              hint: 'Re-enter your password',
              prefixIcon: Icons.lock_reset_rounded,
              suffixIcon: IconButton(
                icon: Icon(
                  _obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  color: const Color(0xFF718096),
                  size: 20,
                ),
                onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
              ),
            ),
            validator: (v) {
              if (v != _passwordCtrl.text) return 'Passwords do not match';
              return null;
            },
          ),
          const SizedBox(height: 16),

          // Terms Checkbox
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: const Color(0xFFE2E8F0)),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  height: 24,
                  width: 24,
                  child: Checkbox(
                    value: _termsAccepted,
                    activeColor: AppTheme.primary,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(4)),
                    onChanged: (v) => setState(() => _termsAccepted = v ?? false),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      const Text('I agree to EZiCart’s ', style: TextStyle(fontSize: 12, color: Color(0xFF4A5568))),
                      GestureDetector(
                        onTap: () => _showPolicyDialog(
                          'Terms of Service',
                          'By using EZiCart, you agree to comply with authentic buyer terms, dispute guidelines, safe transaction policies, and fair use standards.',
                        ),
                        child: const Text(
                          'Terms of Service',
                          style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const Text(' and ', style: TextStyle(fontSize: 12, color: Color(0xFF4A5568))),
                      GestureDetector(
                        onTap: () => _showPolicyDialog(
                          'Privacy Policy',
                          'We respect your personal data. Your phone, address, and credentials are securely stored and solely utilized for order processing, logistics coordination, and buyer account security.',
                        ),
                        child: const Text(
                          'Privacy Policy',
                          style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const Text('.', style: TextStyle(fontSize: 12, color: Color(0xFF4A5568))),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),

          Row(
            children: [
              Expanded(
                flex: 1,
                child: AuthStyles.secondaryButton(
                  onPressed: auth.isLoading ? null : _prevStep,
                  text: 'Back',
                  leadingIcon: Icons.arrow_back_rounded,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: AuthStyles.gradientButton(
                  onPressed: auth.isLoading ? null : _submitRegistration,
                  text: 'Create Account',
                  isLoading: auth.isLoading,
                  trailingIcon: Icons.check_circle_outline_rounded,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    final isPersonalError = auth.errorMessage != null &&
        (auth.errorMessage!.toLowerCase().contains('email') ||
            auth.errorMessage!.toLowerCase().contains('mobile') ||
            auth.errorMessage!.toLowerCase().contains('contact') ||
            auth.errorMessage!.toLowerCase().contains('birthday') ||
            auth.errorMessage!.toLowerCase().contains('name'));

    return Scaffold(
      backgroundColor: const Color(0xFFF8F9FD),
      body: SingleChildScrollView(
        child: Column(
          children: [
            AuthHeader(
              title: 'Create Account',
              subtitle: 'Join EZiCart for exclusive buyer deals and fast checkout',
              showBack: true,
              onBack: _prevStep,
              headerHeight: 210,
            ),

            Transform.translate(
              offset: const Offset(0, -26),
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 16),
                padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(24),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.06),
                      blurRadius: 20,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _buildStepIndicator(),

                    if (auth.errorMessage != null) ...[
                      AuthStyles.errorBanner(auth.errorMessage!),
                      if (isPersonalError && _currentStep != 0) ...[
                        OutlinedButton.icon(
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppTheme.primary,
                            side: const BorderSide(color: AppTheme.primary),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                          ),
                          onPressed: () => setState(() => _currentStep = 0),
                          icon: const Icon(Icons.edit_outlined, size: 16),
                          label: const Text('Edit Personal Information', style: TextStyle(fontSize: 12)),
                        ),
                        const SizedBox(height: 12),
                      ],
                    ],

                    AnimatedSwitcher(
                      duration: const Duration(milliseconds: 250),
                      child: _currentStep == 0
                          ? _buildStep1()
                          : _currentStep == 1
                              ? _buildStep2()
                              : _buildStep3(),
                    ),

                    const SizedBox(height: 20),
                    Center(
                      child: GestureDetector(
                        onTap: () => Navigator.of(context).pop(),
                        child: const Text.rich(
                          TextSpan(
                            text: 'Already have an account? ',
                            style: TextStyle(color: AppTheme.muted, fontSize: 13),
                            children: [
                              TextSpan(
                                text: 'Sign In',
                                style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold),
                              ),
                            ],
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
    );
  }
}
