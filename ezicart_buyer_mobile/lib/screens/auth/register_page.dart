import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../config/theme.dart';
import '../../providers/auth_provider.dart';
import '../common/psgc_address_form.dart';
import 'otp_verification_page.dart';

class RegisterPage extends StatefulWidget {
  const RegisterPage({super.key});

  @override
  State<RegisterPage> createState() => _RegisterPageState();
}

class _RegisterPageState extends State<RegisterPage> {
  int _currentStep = 0; // 0: Details, 1: Address, 2: Password

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
      helpText: 'Date of Birth (Must be at least 18 years old)',
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
    }
  }

  bool _isPasswordComplex(String pwd) {
    if (pwd.length < 8) return false;
    final hasUpper = pwd.contains(RegExp(r'[A-Z]'));
    final hasLower = pwd.contains(RegExp(r'[a-z]'));
    final hasNumber = pwd.contains(RegExp(r'[0-9]'));
    final hasSymbol = pwd.contains(RegExp(r'[!@#\$%^&*(),.?":{}|<>]'));
    return hasUpper && hasLower && hasNumber && hasSymbol;
  }

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
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
        content: SingleChildScrollView(
          child: Text(content, style: const TextStyle(fontSize: 13, height: 1.4)),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
        ],
      ),
    );
  }

  Widget _buildStepIndicator() {
    final stepTitles = ['Your details', 'Delivery address', 'Password & terms'];

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 16),
      margin: const EdgeInsets.only(bottom: 20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppTheme.line),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Step ${_currentStep + 1} of 3',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: AppTheme.primary,
                ),
              ),
              Text(
                stepTitles[_currentStep],
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.bold,
                  color: AppTheme.ink,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: (_currentStep + 1) / 3,
              backgroundColor: Colors.grey.shade200,
              valueColor: const AlwaysStoppedAnimation<Color>(AppTheme.primary),
              minHeight: 6,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStep1() {
    return Form(
      key: _step1FormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Personal Information',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
          ),
          const SizedBox(height: 4),
          const Text(
            'Enter your full name and primary contact details for account verification.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 16),

          // First Name & Middle Name
          Row(
            children: [
              Expanded(
                flex: 2,
                child: TextFormField(
                  controller: _firstNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'First Name *'),
                  validator: (v) {
                    if (v == null || v.trim().length < 2) {
                      return 'Min 2 characters';
                    }
                    if (!RegExp(r"^[\p{L}\p{M} .\x27-]+$", unicode: true).hasMatch(v.trim())) {
                      return 'Letters only';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                flex: 1,
                child: TextFormField(
                  controller: _middleNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Middle Name'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Last Name & Suffix
          Row(
            children: [
              Expanded(
                flex: 3,
                child: TextFormField(
                  controller: _lastNameCtrl,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Last Name *'),
                  validator: (v) {
                    if (v == null || v.trim().length < 2) {
                      return 'Min 2 characters';
                    }
                    if (!RegExp(r"^[\p{L}\p{M} .\x27-]+$", unicode: true).hasMatch(v.trim())) {
                      return 'Letters only';
                    }
                    return null;
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                flex: 1,
                child: TextFormField(
                  controller: _suffixCtrl,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(labelText: 'Suffix', hintText: 'Jr.'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),

          // Email
          TextFormField(
            controller: _emailCtrl,
            keyboardType: TextInputType.emailAddress,
            autocorrect: false,
            decoration: const InputDecoration(
              labelText: 'Email Address *',
              hintText: 'name@example.com',
              prefixIcon: Icon(Icons.email_outlined, size: 20),
            ),
            validator: (v) {
              if (v == null || v.trim().isEmpty) return 'Email is required';
              if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(v.trim())) {
                return 'Enter a valid email address';
              }
              return null;
            },
          ),
          const SizedBox(height: 12),

          // Contact Number
          TextFormField(
            controller: _contactCtrl,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              labelText: 'Contact Number (PH Mobile) *',
              hintText: '09XXXXXXXXX or +63 9XXXXXXXXX',
              prefixIcon: Icon(Icons.phone_android_outlined, size: 20),
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
          const SizedBox(height: 12),

          // Birthday
          TextFormField(
            controller: _birthdayCtrl,
            readOnly: true,
            onTap: _selectBirthday,
            decoration: const InputDecoration(
              labelText: 'Date of Birth (18+ only) *',
              prefixIcon: Icon(Icons.cake_outlined, size: 20),
              suffixIcon: Icon(Icons.calendar_month_outlined, size: 20),
            ),
            validator: (v) => (v == null || v.trim().isEmpty) ? 'Please select your birthday' : null,
          ),
          const SizedBox(height: 24),

          ElevatedButton(
            onPressed: _nextStep,
            child: const Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Text('Next: Delivery Address'),
                SizedBox(width: 8),
                Icon(Icons.arrow_forward, size: 18),
              ],
            ),
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
          children: [
            const Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Delivery Address',
                    style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
                  ),
                  SizedBox(height: 2),
                  Text(
                    'Optional - you can add or update your address anytime in account settings.',
                    style: TextStyle(fontSize: 12, color: AppTheme.muted),
                  ),
                ],
              ),
            ),
            TextButton(
              onPressed: () {
                // Clear address if skipping
                _addressData = AddressFormData();
                _nextStep();
              },
              child: const Text('Skip for now', style: TextStyle(color: AppTheme.muted, fontSize: 13)),
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
              child: OutlinedButton(
                onPressed: _prevStep,
                child: const Text('Back'),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: ElevatedButton(
                onPressed: _nextStep,
                child: const Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('Next: Password'),
                    SizedBox(width: 6),
                    Icon(Icons.arrow_forward, size: 18),
                  ],
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildStep3() {
    final auth = context.watch<AuthProvider>();

    return Form(
      key: _step3FormKey,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Security & Agreement',
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: AppTheme.ink),
          ),
          const SizedBox(height: 4),
          const Text(
            'Choose a strong password to safeguard your orders and account.',
            style: TextStyle(fontSize: 12, color: AppTheme.muted),
          ),
          const SizedBox(height: 16),

          // Password
          TextFormField(
            controller: _passwordCtrl,
            obscureText: _obscurePassword,
            decoration: InputDecoration(
              labelText: 'Password *',
              prefixIcon: const Icon(Icons.lock_outline, size: 20),
              suffixIcon: IconButton(
                icon: Icon(_obscurePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
              ),
            ),
            validator: (v) {
              if (v == null || v.isEmpty) return 'Password is required';
              if (v.length < 8) return 'Password must be at least 8 characters';
              if (!_isPasswordComplex(v)) {
                return 'Must contain uppercase, lowercase, number, and symbol';
              }
              return null;
            },
          ),
          const SizedBox(height: 8),
          const Text(
            'Must be 8-128 characters with uppercase, lowercase, numbers, and symbols.',
            style: TextStyle(fontSize: 11, color: AppTheme.muted),
          ),
          const SizedBox(height: 14),

          // Confirm Password
          TextFormField(
            controller: _confirmPasswordCtrl,
            obscureText: _obscureConfirmPassword,
            decoration: InputDecoration(
              labelText: 'Confirm Password *',
              prefixIcon: const Icon(Icons.lock_reset_outlined, size: 20),
              suffixIcon: IconButton(
                icon: Icon(_obscureConfirmPassword ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                onPressed: () => setState(() => _obscureConfirmPassword = !_obscureConfirmPassword),
              ),
            ),
            validator: (v) {
              if (v != _passwordCtrl.text) return 'Passwords do not match';
              return null;
            },
          ),
          const SizedBox(height: 16),

          // Terms & Privacy checkbox
          Container(
            padding: const EdgeInsets.all(10),
            decoration: BoxDecoration(
              color: Colors.grey.shade50,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AppTheme.line),
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
                    onChanged: (v) => setState(() => _termsAccepted = v ?? false),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Wrap(
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      const Text('I agree to EZiCart’s ', style: TextStyle(fontSize: 12)),
                      GestureDetector(
                        onTap: () => _showPolicyDialog(
                          'Terms of Service',
                          'By using EZiCart, you agree to comply with all marketplace policies, authentic buyer obligations, dispute handling guidelines, and fair use terms.',
                        ),
                        child: const Text(
                          'Terms of Service',
                          style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const Text(' and ', style: TextStyle(fontSize: 12)),
                      GestureDetector(
                        onTap: () => _showPolicyDialog(
                          'Privacy Policy',
                          'We respect your personal data. Your delivery information and identity credentials are encrypted and solely used for order dispatch, courier coordination, and platform security.',
                        ),
                        child: const Text(
                          'Privacy Policy',
                          style: TextStyle(fontSize: 12, color: AppTheme.primary, fontWeight: FontWeight.bold),
                        ),
                      ),
                      const Text('.', style: TextStyle(fontSize: 12)),
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
                child: OutlinedButton(
                  onPressed: auth.isLoading ? null : _prevStep,
                  child: const Text('Back'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                flex: 2,
                child: ElevatedButton(
                  onPressed: auth.isLoading ? null : _submitRegistration,
                  child: auth.isLoading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Create Buyer Account'),
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

    return Scaffold(
      appBar: AppBar(
        title: const Text('Buyer Registration'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Logo brand kicker
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppTheme.primarySoft,
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Text(
                      'EZiCart',
                      style: TextStyle(color: AppTheme.primary, fontWeight: FontWeight.bold, fontSize: 13),
                    ),
                  ),
                  const SizedBox(width: 8),
                  const Text(
                    'Buyer Onboarding',
                    style: TextStyle(color: AppTheme.muted, fontSize: 12),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              // Step indicator
              _buildStepIndicator(),

              // Global Error Display
              if (auth.errorMessage != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    border: Border.all(color: Colors.red.shade200),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline, color: Colors.red, size: 20),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          auth.errorMessage!,
                          style: TextStyle(color: Colors.red.shade900, fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),

              // Active step view
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                child: _currentStep == 0
                    ? _buildStep1()
                    : _currentStep == 1
                        ? _buildStep2()
                        : _buildStep3(),
              ),

              const SizedBox(height: 24),
              Center(
                child: TextButton(
                  onPressed: () => Navigator.of(context).pop(),
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
    );
  }
}
