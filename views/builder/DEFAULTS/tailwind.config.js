module.exports = {   
  darkMode: "class",               
  daisyui: {  
    themes: [...AgnosticThemes],                      
  }, 
  theme: {  
    fontFamily: AgnosticFonts,
  },
  plugins: [require("daisyui")],
  corePlugins: {
    preflight: true,
  },
}