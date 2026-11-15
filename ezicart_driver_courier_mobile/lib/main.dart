import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config/theme.dart';
import 'providers/auth_provider.dart';
import 'providers/courier_provider.dart';
import 'screens/auth/login_page.dart';
import 'screens/courier/courier_dashboard.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const EZiCartCourierApp());
}

class EZiCartCourierApp extends StatelessWidget {
  const EZiCartCourierApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => AuthProvider()..tryAutoLogin()),
        ChangeNotifierProvider(create: (_) => CourierProvider()),
      ],
      child: MaterialApp(
        title: 'EZiCart Driver & Courier',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.lightTheme,
        home: const AuthWrapper(),
      ),
    );
  }
}

class AuthWrapper extends StatelessWidget {
  const AuthWrapper({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    if (!auth.isInitialized) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(color: AppTheme.primary),
        ),
      );
    }
    if (auth.isAuthenticated) {
      return const CourierDashboard();
    }
    return const LoginPage();
  }
}
