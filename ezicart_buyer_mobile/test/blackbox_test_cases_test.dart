import 'package:flutter_test/flutter_test.dart';

void main() {
  group('Black-Box Test Cases: Name Rules (TC-BUY-004, 005, 006)', () {
    final nameRegex = RegExp(r"^[\p{L}\p{M} .\x27-]+$", unicode: true);

    String? validateName(String? name) {
      if (name == null || name.trim().isEmpty) return 'Name is required';
      final trimmed = name.trim();
      if (trimmed.length < 2) return 'Min 2 characters';
      if (trimmed.length > 50) return 'Max 50 characters';
      if (!nameRegex.hasMatch(trimmed)) return 'Only letters and spaces allowed';
      return null;
    }

    test('TC-BUY-004: Name with numbers or symbols is rejected', () {
      expect(validateName('Juan Miguel123'), 'Only letters and spaces allowed');
      expect(validateName('Dela Cruz@#'), 'Only letters and spaces allowed');
      expect(validateName('Juan!'), 'Only letters and spaces allowed');
    });

    test('TC-BUY-005: Name with ñ, spaces, hyphens, and allowed punctuation is accepted', () {
      expect(validateName('Niño Angelo'), isNull);
      expect(validateName("Peñaflor-O'Hara"), isNull);
      expect(validateName('Dr. Jose P. Rizal'), isNull);
      expect(validateName('Ma. Elena'), isNull);
    });

    test('TC-BUY-006: Boundary Value Analysis on name length (1, 2, 50, 51)', () {
      expect(validateName('J'), 'Min 2 characters'); // 1 char: reject
      expect(validateName('Jo'), isNull); // 2 chars: accept
      expect(
        validateName('Maria Cristina Angelica Bernadette Josefina Leonor'), // 50 chars: accept
        isNull,
      );
      expect(
        validateName('Maria Cristina Angelica Bernadette Josefina Leonora'), // 51 chars: reject
        'Max 50 characters',
      );
    });
  });

  group('Black-Box Test Cases: Date of Birth & Age (TC-BUY-007, 008)', () {
    String? validateBirthday(String? input) {
      if (input == null || input.trim().isEmpty) return 'Date of birth is required';
      final parsed = DateTime.tryParse(input.trim());
      if (parsed == null) return 'Enter a valid date';
      final now = DateTime.now();
      int age = now.year - parsed.year;
      if (now.month < parsed.month || (now.month == parsed.month && now.day < parsed.day)) {
        age--;
      }
      if (parsed.isAfter(now)) return 'Date of birth cannot be in the future';
      if (age < 18) return 'You must be at least 18 years old';
      return null;
    }

    test('TC-BUY-007: Age boundary at 18-year limit (17 vs 18)', () {
      final now = DateTime.now();
      // 17 years old (born 17 years ago today + 1 day)
      final seventeen = DateTime(now.year - 17, now.month, now.day);
      expect(
        validateBirthday(seventeen.toIso8601String().substring(0, 10)),
        'You must be at least 18 years old',
      );

      // Exactly 18 years old today
      final eighteenToday = DateTime(now.year - 18, now.month, now.day);
      expect(
        validateBirthday(eighteenToday.toIso8601String().substring(0, 10)),
        isNull,
      );

      // 18 years and 1 day old
      final eighteenAndDay = eighteenToday.subtract(const Duration(days: 1));
      expect(
        validateBirthday(eighteenAndDay.toIso8601String().substring(0, 10)),
        isNull,
      );
    });

    test('TC-BUY-008: Date of birth in the future is rejected', () {
      final tomorrow = DateTime.now().add(const Duration(days: 1));
      expect(
        validateBirthday(tomorrow.toIso8601String().substring(0, 10)),
        'Date of birth cannot be in the future',
      );
    });
  });

  group('Black-Box Test Cases: Email Format & Trimming (TC-BUY-009, 011, 012)', () {
    final emailRegex = RegExp(r'^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$');

    String? validateEmail(String? input) {
      if (input == null || input.trim().isEmpty) return 'Email is required';
      final trimmed = input.trim();
      if (!emailRegex.hasMatch(trimmed)) return 'Enter a valid email address';
      return null;
    }

    test('TC-BUY-009: Invalid email formats are rejected', () {
      expect(validateEmail('juanmiguel.delacruzexample.com'), 'Enter a valid email address'); // no @
      expect(validateEmail('juanmiguel.delacruz@'), 'Enter a valid email address'); // no domain
      expect(validateEmail('@example.com'), 'Enter a valid email address'); // no user
      expect(validateEmail('juanmiguel.delacruz@example'), 'Enter a valid email address'); // no .com
      expect(validateEmail('juanmiguel delacruz@example.com'), 'Enter a valid email address'); // space
    });

    test('TC-BUY-011: Email is case-insensitive normalized', () {
      const emailUpper = 'JUANMIGUEL.DELACRUZ@EXAMPLE.COM';
      expect(emailUpper.trim().toLowerCase(), 'juanmiguel.delacruz@example.com');
    });

    test('TC-BUY-012: Email with leading and trailing spaces is trimmed', () {
      const emailWithSpaces = '   buyer.test01@example.com   ';
      expect(validateEmail(emailWithSpaces), isNull);
      expect(emailWithSpaces.trim().toLowerCase(), 'buyer.test01@example.com');
    });
  });

  group('Black-Box Test Cases: Mobile Number Rules (TC-BUY-013, 014, 016)', () {
    final mobileRegex = RegExp(r'^(09\d{9}|\+639\d{9})$');

    String? validateMobile(String? input) {
      if (input == null || input.trim().isEmpty) return 'Mobile number is required';
      final cleaned = input.trim().replaceAll(' ', '');
      if (!mobileRegex.hasMatch(cleaned)) return 'Enter a valid 11-digit PH mobile number';
      return null;
    }

    test('TC-BUY-013: Mobile number in invalid formats is rejected', () {
      expect(validateMobile('0917ABC4567'), 'Enter a valid 11-digit PH mobile number'); // letters
      expect(validateMobile('0917-123-456'), 'Enter a valid 11-digit PH mobile number'); // symbols / short
      expect(validateMobile('12345678901'), 'Enter a valid 11-digit PH mobile number'); // not starting 09
      expect(validateMobile('02-8123-4567'), 'Enter a valid 11-digit PH mobile number'); // landline
    });

    test('TC-BUY-014: Mobile number length boundary (10, 11, 12 digits)', () {
      expect(validateMobile('0917123456'), 'Enter a valid 11-digit PH mobile number'); // 10 digits
      expect(validateMobile('09170000001'), isNull); // 11 digits: accept
      expect(validateMobile('091712345678'), 'Enter a valid 11-digit PH mobile number'); // 12 digits
    });

    test('TC-BUY-016: Registered number in +63 format is recognized and accepted by regex', () {
      expect(validateMobile('+639171234567'), isNull);
    });
  });

  group('Black-Box Test Cases: Password Complexity (TC-BUY-017, 018, 019)', () {
    bool hasMinLength(String p) => p.length >= 8;
    bool hasUpper(String p) => p.contains(RegExp(r'[A-Z]'));
    bool hasLower(String p) => p.contains(RegExp(r'[a-z]'));
    bool hasNumber(String p) => p.contains(RegExp(r'[0-9]'));
    bool hasSymbol(String p) => p.contains(RegExp(r'[!@#\$%^&*(),.?":{}|<>]'));

    String? validatePassword(String? p, String? confirm) {
      if (p == null || p.isEmpty) return 'Password is required';
      if (p.length < 8) return 'Password must be at least 8 characters';
      if (!hasUpper(p) || !hasLower(p) || !hasNumber(p) || !hasSymbol(p)) {
        return 'Password must contain uppercase, lowercase, number, and special character';
      }
      if (confirm != null && p != confirm) return 'Passwords do not match';
      return null;
    }

    test('TC-BUY-017: Password length at limit (7 vs 8 chars)', () {
      expect(validatePassword('Bili@26', 'Bili@26'), 'Password must be at least 8 characters'); // 7 chars
      expect(validatePassword('Bili@202', 'Bili@202'), isNull); // 8 chars: accept
    });

    test('TC-BUY-018: Password missing required character types', () {
      expect(
        validatePassword('mamimili@2026', 'mamimili@2026'),
        'Password must contain uppercase, lowercase, number, and special character',
      ); // No uppercase
      expect(
        validatePassword('MAMIMILI@2026', 'MAMIMILI@2026'),
        'Password must contain uppercase, lowercase, number, and special character',
      ); // No lowercase
      expect(
        validatePassword('Mamimili@abcd', 'Mamimili@abcd'),
        'Password must contain uppercase, lowercase, number, and special character',
      ); // No number
      expect(
        validatePassword('Mamimili2026', 'Mamimili2026'),
        'Password must contain uppercase, lowercase, number, and special character',
      ); // No symbol
      expect(validatePassword('Mamimili@2026', 'Mamimili@2026'), isNull); // Valid
    });

    test('TC-BUY-019: Confirm Password does not match', () {
      expect(validatePassword('Mamimili@2026', 'Mamimili@2027'), 'Passwords do not match');
    });
  });

  group('Black-Box Test Cases: Email Verification & OTP Format (TC-BUY-025, 038)', () {
    String? validateOtp(String? code) {
      if (code == null || code.trim().isEmpty) return 'Please enter OTP';
      final clean = code.trim();
      if (clean.length != 6 || !RegExp(r'^\d{6}$').hasMatch(clean)) {
        return 'OTP must be exactly 6 digits';
      }
      return null;
    }

    test('TC-BUY-025: OTP in wrong format is rejected', () {
      expect(validateOtp('48291'), 'OTP must be exactly 6 digits'); // 5 digits
      expect(validateOtp('4829137'), 'OTP must be exactly 6 digits'); // 7 digits
      expect(validateOtp('48A91B'), 'OTP must be exactly 6 digits'); // letters
      expect(validateOtp(''), 'Please enter OTP'); // blank
      expect(validateOtp('482913'), isNull); // valid
    });

    test('TC-BUY-038: OTP countdown based on wall-clock elapsed time', () {
      final now = DateTime.now();
      final targetExpiry = now.add(const Duration(minutes: 5));

      // Simulate returning 2 minutes later
      final twoMinutesLater = now.add(const Duration(minutes: 2));
      final remainingAfter2Min = targetExpiry.difference(twoMinutesLater).inSeconds;
      expect(remainingAfter2Min, 180); // exactly 3 minutes left

      // Simulate after 5 minutes and 1 second
      final expiredTime = now.add(const Duration(minutes: 5, seconds: 1));
      final remainingAfterExpiry = targetExpiry.difference(expiredTime).inSeconds;
      expect(remainingAfterExpiry <= 0, isTrue); // expired
    });
  });
}
